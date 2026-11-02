<?php

namespace App\Services;

use App\Enums\SavingsPayoutStatus;
use App\Models\Account;
use App\Models\JournalLine;
use App\Models\SavingsAccount;
use App\Models\SavingsPayoutBatch;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingsPayoutService
{
    public function __construct(
        private readonly HijriCalendarService $calendar,
        private readonly SavingsService $savings,
        private readonly AuditService $audit,
    ) {}

    public function create(int $hijriYear, ?string $payoutDate, int $userId): SavingsPayoutBatch
    {
        return DB::transaction(function () use ($hijriYear, $payoutDate, $userId): SavingsPayoutBatch {
            if ($hijriYear !== $this->calendar->nextPayout()['hijri_year']) {
                throw ValidationException::withMessages([
                    'hijri_year' => 'Batch hanya dapat dibuat untuk jadwal H-10 Idulfitri berikutnya.',
                ]);
            }
            if (SavingsPayoutBatch::query()->where('hijri_year', $hijriYear)->exists()) {
                throw ValidationException::withMessages([
                    'hijri_year' => 'Batch pencairan untuk tahun Hijriah ini sudah dibuat.',
                ]);
            }

            $calculatedDate = $this->calendar->payoutDateForYear($hijriYear);
            $confirmedDate = $payoutDate
                ? CarbonImmutable::parse($payoutDate, config('app.timezone'))->startOfDay()
                : $calculatedDate;

            if (abs($confirmedDate->diffInDays($calculatedDate, false)) > 2) {
                throw ValidationException::withMessages([
                    'payout_date' => 'Tanggal konfirmasi harus berada dalam rentang dua hari dari perkiraan Umm al-Qura.',
                ]);
            }

            $accounts = SavingsAccount::query()
                ->where('status', 'active')
                ->where('current_balance', '>', 0)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($accounts->isEmpty()) {
                throw ValidationException::withMessages([
                    'batch' => 'Tidak ada saldo tabungan anggota yang dapat dicairkan.',
                ]);
            }

            $totalDue = $accounts->sum(fn (SavingsAccount $account): int => Money::cents($account->current_balance));
            $batch = SavingsPayoutBatch::query()->create([
                'hijri_year' => $hijriYear,
                'payout_date' => $confirmedDate->toDateString(),
                'status' => SavingsPayoutStatus::PendingApproval,
                'total_due' => Money::decimal($totalDue),
                'total_paid' => '0.00',
                'created_by' => $userId,
            ]);

            foreach ($accounts as $account) {
                $batch->items()->create([
                    'savings_account_id' => $account->id,
                    'amount_due' => $account->current_balance,
                    'amount_paid' => '0.00',
                ]);
            }

            $this->audit->record('savings', 'annual_payout_created', $batch, null, [
                'hijri_year' => $hijriYear,
                'payout_date' => $confirmedDate->toDateString(),
                'total_due' => Money::decimal($totalDue),
                'member_count' => $accounts->count(),
            ]);

            return $batch->loadCount('items');
        });
    }

    public function approve(SavingsPayoutBatch $batch, int $userId): SavingsPayoutBatch
    {
        return DB::transaction(function () use ($batch, $userId): SavingsPayoutBatch {
            $locked = SavingsPayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if ($locked->status !== SavingsPayoutStatus::PendingApproval) {
                throw ValidationException::withMessages([
                    'batch' => 'Batch ini tidak lagi menunggu persetujuan.',
                ]);
            }

            $locked->update([
                'status' => SavingsPayoutStatus::Approved,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);
            $this->audit->record('savings', 'annual_payout_approved', $locked, [
                'status' => SavingsPayoutStatus::PendingApproval->value,
            ], [
                'status' => SavingsPayoutStatus::Approved->value,
                'approved_by' => $userId,
            ]);

            return $locked->fresh();
        });
    }

    public function process(SavingsPayoutBatch $batch, int $userId): SavingsPayoutBatch
    {
        return DB::transaction(function () use ($batch, $userId): SavingsPayoutBatch {
            $locked = SavingsPayoutBatch::query()->lockForUpdate()->findOrFail($batch->id);
            if (! in_array($locked->status, [SavingsPayoutStatus::Approved, SavingsPayoutStatus::PartiallyPaid], true)) {
                throw ValidationException::withMessages([
                    'batch' => 'Batch harus disetujui Ketua sebelum diproses.',
                ]);
            }
            if ($locked->payout_date->isFuture()) {
                throw ValidationException::withMessages([
                    'batch' => 'Batch belum dapat diproses sebelum tanggal pencairan yang dikonfirmasi.',
                ]);
            }

            Account::query()->where('account_code', '1001')->lockForUpdate()->firstOrFail();
            $cashBalance = $this->cashBalanceInCents();
            if ($cashBalance <= 0) {
                throw ValidationException::withMessages([
                    'batch' => 'Kas tersedia tidak mencukupi untuk pencairan saat ini.',
                ]);
            }

            $previousStatus = $locked->status->value;
            $previousTotalPaid = $locked->total_paid;
            $items = $locked->items()
                ->with('savingsAccount')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $caps = [];
            foreach ($items as $item) {
                $remainingDue = Money::cents($item->amount_due) - Money::cents($item->amount_paid);
                $currentBalance = Money::cents($item->savingsAccount->current_balance);
                $caps[$item->id] = max(0, min($remainingDue, $currentBalance));
            }

            $totalEligible = array_sum($caps);
            if ($totalEligible <= 0) {
                throw ValidationException::withMessages([
                    'batch' => 'Tidak ada saldo anggota yang tersisa untuk dibayarkan dalam batch ini.',
                ]);
            }

            $allocations = $this->proRataAllocations($caps, min($cashBalance, $totalEligible));
            $totalPaidNow = 0;
            foreach ($items as $item) {
                $amount = $allocations[$item->id] ?? 0;
                if ($amount <= 0) {
                    continue;
                }

                $this->savings->transact($item->savingsAccount, [
                    'type' => 'withdrawal',
                    'amount' => Money::decimal($amount),
                    'transaction_date' => now()->toDateString(),
                    'description' => 'Pencairan tabungan H-10 Idulfitri '.$locked->hijri_year,
                    'savings_payout_item_id' => $item->id,
                ], $userId);

                $item->update([
                    'amount_paid' => Money::decimal(Money::cents($item->amount_paid) + $amount),
                ]);
                $totalPaidNow += $amount;
            }

            $totalPaid = Money::cents($locked->total_paid) + $totalPaidNow;
            $totalDue = Money::cents($locked->total_due);
            $locked->update([
                'status' => $totalPaid >= $totalDue
                    ? SavingsPayoutStatus::Paid
                    : SavingsPayoutStatus::PartiallyPaid,
                'total_paid' => Money::decimal($totalPaid),
                'last_processed_by' => $userId,
                'last_processed_at' => now(),
            ]);
            $this->audit->record('savings', 'annual_payout_processed', $locked, [
                'total_paid' => $previousTotalPaid,
                'status' => $previousStatus,
            ], [
                'total_paid' => Money::decimal($totalPaid),
                'paid_this_time' => Money::decimal($totalPaidNow),
                'status' => $locked->status->value,
                'processed_by' => $userId,
            ]);

            return $locked->fresh();
        });
    }

    public function availableCash(): string
    {
        return Money::decimal(max(0, $this->cashBalanceInCents()));
    }

    private function cashBalanceInCents(): int
    {
        $balance = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('accounts.account_code', '1001')
            ->where('journal_entries.status', 'posted')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) AS balance')
            ->value('balance');

        return Money::cents((string) ($balance ?? '0'));
    }

    /**
     * @param  array<int, int>  $caps
     * @return array<int, int>
     */
    private function proRataAllocations(array $caps, int $amount): array
    {
        $total = array_sum($caps);
        if ($amount >= $total) {
            return $caps;
        }

        $allocations = [];
        $allocated = 0;
        foreach ($caps as $itemId => $cap) {
            $share = (int) floor(($cap / $total) * $amount);
            $allocations[$itemId] = min($cap, $share);
            $allocated += $allocations[$itemId];
        }

        $remainder = $amount - $allocated;
        foreach ($caps as $itemId => $cap) {
            if ($remainder <= 0) {
                break;
            }
            if ($allocations[$itemId] < $cap) {
                $allocations[$itemId]++;
                $remainder--;
            }
        }

        return $allocations;
    }
}
