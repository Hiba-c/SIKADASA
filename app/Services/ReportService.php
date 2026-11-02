<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\ArisanCycle;
use App\Models\ArisanPayment;
use App\Models\ArisanPayout;
use App\Models\CashTransaction;
use App\Models\Loan;
use App\Models\OrganizationSetting;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public function build(string $type, CarbonImmutable $from, CarbonImmutable $to, bool $includeRecordedAt = false): array
    {
        return match ($type) {
            'cash' => $this->cash($from, $to, $includeRecordedAt),
            'savings' => $this->savings($from, $to, $includeRecordedAt),
            'loans' => $this->loans($from, $to, $includeRecordedAt),
            'arisan' => $this->arisan($from, $to, $includeRecordedAt),
            'financial' => $this->financial($from, $to),
            default => throw new \InvalidArgumentException('Unsupported report type.'),
        };
    }

    private function cash(CarbonImmutable $from, CarbonImmutable $to, bool $includeRecordedAt): array
    {
        $transactions = CashTransaction::query()
            ->with('category')
            ->where('status', TransactionStatus::Posted)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        $income = $transactions->where('transaction_type', TransactionType::Income)->sum(fn ($row) => Money::cents($row->amount));
        $expense = $transactions->where('transaction_type', TransactionType::Expense)->sum(fn ($row) => Money::cents($row->amount));

        return [
            'title' => 'Laporan Kas',
            'summary' => [
                ['label' => 'Pemasukan', 'amount' => Money::decimal($income)],
                ['label' => 'Pengeluaran', 'amount' => Money::decimal($expense)],
                ['label' => 'Selisih', 'amount' => Money::decimal($income - $expense)],
                ['label' => 'Jumlah transaksi', 'amount' => (string) $transactions->count()],
            ],
            'columns' => ['Nomor', 'Tanggal', 'Jenis', 'Pembayar / penerima', 'Kategori', 'Keterangan', 'Jumlah', ...($includeRecordedAt ? ['Waktu dicatat'] : [])],
            'rows' => $transactions->map(fn (CashTransaction $row): array => [
                $row->transaction_number,
                $row->transaction_date->toDateString(),
                $row->transaction_type->value,
                $row->payer_name ?? '',
                $row->category->name,
                $row->description ?? '',
                $row->amount,
                ...($includeRecordedAt ? [$row->created_at->toIso8601String()] : []),
            ])->all(),
        ];
    }

    private function savings(CarbonImmutable $from, CarbonImmutable $to, bool $includeRecordedAt): array
    {
        $transactions = SavingsTransaction::query()
            ->with('savingsAccount.member')
            ->where('status', TransactionStatus::Posted)
            ->whereBetween('transaction_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('transaction_date')
            ->orderBy('id')
            ->get();
        $deposits = $transactions->where('type', 'deposit')->sum(fn ($row) => Money::cents($row->amount));
        $withdrawals = $transactions->where('type', 'withdrawal')->sum(fn ($row) => Money::cents($row->amount));
        $currentBalance = SavingsAccount::query()->sum('current_balance');

        return [
            'title' => 'Laporan Tabungan',
            'summary' => [
                ['label' => 'Setoran', 'amount' => Money::decimal($deposits)],
                ['label' => 'Penarikan', 'amount' => Money::decimal($withdrawals)],
                ['label' => 'Perubahan saldo', 'amount' => Money::decimal($deposits - $withdrawals)],
                ['label' => 'Saldo terkini', 'amount' => Money::decimal(Money::cents($currentBalance))],
            ],
            'columns' => ['Nomor', 'Tanggal', 'Anggota', 'Jenis', 'Jumlah', 'Keterangan', ...($includeRecordedAt ? ['Waktu dicatat'] : [])],
            'rows' => $transactions->map(fn (SavingsTransaction $row): array => [
                $row->transaction_number,
                $row->transaction_date->toDateString(),
                $row->savingsAccount->member->full_name,
                $row->type,
                $row->amount,
                $row->description ?? '',
                ...($includeRecordedAt ? [$row->created_at->toIso8601String()] : []),
            ])->all(),
        ];
    }

    private function loans(CarbonImmutable $from, CarbonImmutable $to, bool $includeRecordedAt): array
    {
        $loans = Loan::query()
            ->with('member', 'installments')
            ->whereBetween('application_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('application_date')
            ->orderBy('id')
            ->get();
        $disbursed = $loans->whereIn('status', [LoanStatus::Active, LoanStatus::PaidOff])
            ->sum(fn (Loan $loan) => Money::cents($loan->principal_amount));
        $outstanding = $loans->where('status', LoanStatus::Active)->sum(fn (Loan $loan): int => $loan->installments->sum(
            fn ($installment): int => max(0, Money::cents($installment->principal_due) - Money::cents($installment->principal_paid))
                + max(0, Money::cents($installment->service_due) - Money::cents($installment->service_paid)),
        ));

        return [
            'title' => 'Laporan Pinjaman',
            'summary' => [
                ['label' => 'Pengajuan', 'amount' => (string) $loans->count()],
                ['label' => 'Pinjaman dicairkan', 'amount' => Money::decimal($disbursed)],
                ['label' => 'Sisa kewajiban pinjaman aktif', 'amount' => Money::decimal($outstanding)],
            ],
            'columns' => ['Nomor', 'Tanggal pengajuan', 'Anggota', 'Pokok', 'Jasa', 'Total kewajiban', 'Status', ...($includeRecordedAt ? ['Waktu dicatat'] : [])],
            'rows' => $loans->map(fn (Loan $loan): array => [
                $loan->loan_number,
                $loan->application_date->toDateString(),
                $loan->member->full_name,
                $loan->principal_amount,
                $loan->service_amount,
                $loan->total_obligation,
                $loan->status->value,
                ...($includeRecordedAt ? [$loan->created_at->toIso8601String()] : []),
            ])->all(),
        ];
    }

    private function arisan(CarbonImmutable $from, CarbonImmutable $to, bool $includeRecordedAt): array
    {
        $payments = ArisanPayment::query()
            ->with('participant.member', 'round.cycle')
            ->where('status', TransactionStatus::Posted)
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payment_date')
            ->orderBy('id')
            ->get();
        $payouts = ArisanPayout::query()
            ->with('winner.member', 'round.cycle')
            ->where('status', 'paid')
            ->whereBetween('payout_date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('payout_date')
            ->orderBy('id')
            ->get();
        $contributions = $payments->sum(fn ($row) => Money::cents($row->amount));
        $distributed = $payouts->sum(fn ($row) => Money::cents($row->amount));

        return [
            'title' => 'Laporan Arisan',
            'summary' => [
                ['label' => 'Iuran terkumpul', 'amount' => Money::decimal($contributions)],
                ['label' => 'Penyaluran', 'amount' => Money::decimal($distributed)],
                ['label' => 'Jumlah pembayaran', 'amount' => (string) $payments->count()],
                ['label' => 'Jumlah penyaluran', 'amount' => (string) $payouts->count()],
            ],
            'columns' => ['Nomor', 'Tanggal', 'Siklus', 'Peserta / Pemenang', 'Jenis', 'Jumlah', ...($includeRecordedAt ? ['Waktu dicatat'] : [])],
            'rows' => [
                ...$payments->map(fn (ArisanPayment $row): array => [
                    $row->transaction_number,
                    $row->payment_date->toDateString(),
                    $row->round->cycle->name,
                    $row->participant->member->full_name,
                    'iuran',
                    $row->amount,
                    ...($includeRecordedAt ? [$row->created_at->toIso8601String()] : []),
                ])->all(),
                ...$payouts->map(fn (ArisanPayout $row): array => [
                    $row->payout_number,
                    $row->payout_date->toDateString(),
                    $row->round->cycle->name,
                    $row->winner->member->full_name,
                    'penyaluran',
                    $row->amount,
                    ...($includeRecordedAt ? [$row->updated_at->toIso8601String()] : []),
                ])->all(),
            ],
        ];
    }

    private function financial(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $balances = DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.transaction_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('accounts.id', 'accounts.account_code', 'accounts.account_name', 'accounts.account_type')
            ->orderBy('accounts.account_code')
            ->select([
                'accounts.account_code',
                'accounts.account_name',
                'accounts.account_type',
                DB::raw('SUM(journal_lines.debit) as debit'),
                DB::raw('SUM(journal_lines.credit) as credit'),
            ])
            ->get();
        $debit = $balances->sum(fn ($row) => Money::cents($row->debit));
        $credit = $balances->sum(fn ($row) => Money::cents($row->credit));
        $consolidated = $this->consolidatedPosition($from, $to);

        return [
            'title' => 'Laporan Keuangan',
            'summary' => [
                ['label' => 'Total debit', 'amount' => Money::decimal($debit)],
                ['label' => 'Total kredit', 'amount' => Money::decimal($credit)],
                ['label' => 'Selisih jurnal', 'amount' => Money::decimal($debit - $credit)],
                ['label' => 'Status', 'amount' => $debit === $credit ? 'Seimbang' : 'Perlu diperiksa'],
            ],
            'columns' => ['Kode akun', 'Nama akun', 'Jenis', 'Debit', 'Kredit', 'Saldo normal'],
            'rows' => $balances->map(function ($row): array {
                $debitCents = Money::cents($row->debit);
                $creditCents = Money::cents($row->credit);
                $normalDebit = in_array($row->account_type, ['asset', 'expense'], true);

                return [
                    $row->account_code,
                    $row->account_name,
                    $row->account_type,
                    Money::decimal($debitCents),
                    Money::decimal($creditCents),
                    Money::decimal($normalDebit ? $debitCents - $creditCents : $creditCents - $debitCents),
                ];
            })->all(),
            'consolidated' => $consolidated,
        ];
    }

    private function consolidatedPosition(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $cashTransactions = CashTransaction::query()
            ->where('status', TransactionStatus::Posted)
            ->whereDate('transaction_date', '<=', $to->toDateString())
            ->get(['transaction_type', 'transaction_date', 'amount']);
        $cashIncome = $cashTransactions
            ->where('transaction_type', TransactionType::Income)
            ->sum(fn (CashTransaction $transaction): int => Money::cents($transaction->amount));
        $cashExpense = $cashTransactions
            ->where('transaction_type', TransactionType::Expense)
            ->sum(fn (CashTransaction $transaction): int => Money::cents($transaction->amount));
        $periodIncome = $cashTransactions
            ->where('transaction_type', TransactionType::Income)
            ->filter(fn (CashTransaction $transaction): bool => $transaction->transaction_date->greaterThanOrEqualTo($from))
            ->sum(fn (CashTransaction $transaction): int => Money::cents($transaction->amount));
        $periodExpense = $cashTransactions
            ->where('transaction_type', TransactionType::Expense)
            ->filter(fn (CashTransaction $transaction): bool => $transaction->transaction_date->greaterThanOrEqualTo($from))
            ->sum(fn (CashTransaction $transaction): int => Money::cents($transaction->amount));
        $cashBalance = Money::cents(DB::table('journal_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereDate('journal_entries.transaction_date', '<=', $to->toDateString())
            ->where('accounts.account_code', '1001')
            ->selectRaw('COALESCE(SUM(journal_lines.debit - journal_lines.credit), 0) as balance')
            ->value('balance'));

        $accounts = SavingsAccount::query()
            ->with([
                'member',
                'transactions' => fn ($query) => $query
                    ->where('status', TransactionStatus::Posted)
                    ->whereDate('transaction_date', '<=', $to->toDateString())
                    ->orderBy('transaction_date')
                    ->orderBy('id'),
            ])
            ->whereHas('member')
            ->get()
            ->sortBy(fn (SavingsAccount $account): string => $account->member->full_name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
        $savingsRows = $accounts->map(function (SavingsAccount $account): array {
            $deposits = $account->transactions
                ->where('type', 'deposit')
                ->sum(fn (SavingsTransaction $transaction): int => Money::cents($transaction->amount));
            $withdrawals = $account->transactions
                ->where('type', 'withdrawal')
                ->sum(fn (SavingsTransaction $transaction): int => Money::cents($transaction->amount));

            return [
                'member' => $account->member->full_name,
                'unit' => $account->member->house_number ? 'Rumah '.$account->member->house_number : '—',
                'deposits' => Money::decimal($deposits),
                'withdrawals' => Money::decimal($withdrawals),
                'balance' => Money::decimal($deposits - $withdrawals),
            ];
        });
        $totalSavings = $savingsRows->sum(fn (array $row): int => Money::cents($row['balance']));

        $loans = Loan::query()
            ->with([
                'installments',
                'payments' => fn ($query) => $query
                    ->where('status', TransactionStatus::Posted)
                    ->whereDate('payment_date', '<=', $to->toDateString()),
            ])
            ->whereNotNull('disbursed_at')
            ->whereDate('disbursed_at', '<=', $to->toDateString())
            ->whereNotIn('status', ['rejected', 'cancelled'])
            ->get();
        $loanBalances = $loans->map(function (Loan $loan) use ($to): array {
            $paid = $loan->payments->sum(fn ($payment): int => Money::cents($payment->total_amount));
            $remaining = max(0, Money::cents($loan->total_obligation) - $paid);
            $overdue = $remaining > 0 && $loan->installments->contains(
                fn ($installment): bool => $installment->due_date->lessThanOrEqualTo($to)
                    && Money::cents($installment->principal_due) + Money::cents($installment->service_due)
                        > Money::cents($installment->principal_paid) + Money::cents($installment->service_paid),
            );

            return ['remaining' => $remaining, 'overdue' => $overdue];
        })->filter(fn (array $row): bool => $row['remaining'] > 0);
        $loanBalance = $loanBalances->sum('remaining');
        $overdueLoans = $loanBalances->where('overdue', true)->count();

        $cycles = ArisanCycle::query()
            ->with([
                'rounds.payments' => fn ($query) => $query
                    ->where('status', 'posted')
                    ->whereDate('payment_date', '<=', $to->toDateString()),
                'rounds.payouts' => fn ($query) => $query
                    ->where('status', 'paid')
                    ->whereDate('payout_date', '<=', $to->toDateString()),
                'rounds.payouts.winner',
            ])
            ->whereDate('started_at', '<=', $to->toDateString())
            ->where(function ($query) use ($to): void {
                $query->whereNull('completed_at')->orWhereDate('completed_at', '>', $to->toDateString());
            })
            ->where('status', '!=', 'cancelled')
            ->get();
        $arisanCollected = $cycles->sum(fn (ArisanCycle $cycle): int => $cycle->rounds
            ->flatMap->payments
            ->sum(fn (ArisanPayment $payment): int => Money::cents($payment->amount)));
        $arisanPaidOut = $cycles->sum(fn (ArisanCycle $cycle): int => $cycle->rounds
            ->flatMap->payouts
            ->sum(fn (ArisanPayout $payout): int => Money::cents($payout->amount)));
        $arisanRecipients = $cycles->flatMap(fn (ArisanCycle $cycle) => $cycle->rounds)
            ->flatMap->payouts
            ->map(fn (ArisanPayout $payout): ?int => $payout->winner?->member_id)
            ->filter()
            ->unique()
            ->count();

        $periodLabel = $from->isSameMonth($to)
            ? $from->locale('id')->translatedFormat('F Y')
            : $from->locale('id')->translatedFormat('d M Y').' – '.$to->locale('id')->translatedFormat('d M Y');

        return [
            'period_label' => $periodLabel,
            'fiscal_year' => $from->year === $to->year ? $to->format('Y') : $from->format('Y').'–'.$to->format('Y'),
            'summary' => [
                [
                    'label' => 'Saldo Kas Tunai Kelompok',
                    'amount' => Money::decimal($cashBalance),
                    'note' => 'Pemasukan '.$this->rupiah($periodIncome).' - Pengeluaran '.$this->rupiah($periodExpense),
                ],
                [
                    'label' => 'Total Saldo Tabungan Sukarela',
                    'amount' => Money::decimal($totalSavings),
                    'note' => 'Dana simpanan sukarela anggota hingga '.$to->locale('id')->translatedFormat('d F Y'),
                ],
                [
                    'label' => 'Sisa Piutang Pinjaman Beredar',
                    'amount' => Money::decimal($loanBalance),
                    'note' => $loanBalances->count().' pinjaman aktif ('.$overdueLoans.' menunggak)',
                ],
                [
                    'label' => 'Putaran Arisan Berjalan',
                    'amount' => Money::decimal($arisanCollected - $arisanPaidOut),
                    'note' => $arisanRecipients.' anggota sudah menerima giliran',
                ],
            ],
            'savings_rows' => $savingsRows->all(),
        ];
    }

    private function rupiah(int $amount): string
    {
        return 'Rp '.number_format($amount / 100, 0, ',', '.');
    }

    public function reconcile(): array
    {
        $unbalancedJournals = DB::table('journal_entries')
            ->join('journal_lines', 'journal_lines.journal_entry_id', '=', 'journal_entries.id')
            ->where('journal_entries.status', 'posted')
            ->groupBy('journal_entries.id')
            ->havingRaw('SUM(journal_lines.debit) <> SUM(journal_lines.credit)')
            ->get(['journal_entries.id'])
            ->count();

        $expectedBalances = DB::table('savings_transactions')
            ->where('status', TransactionStatus::Posted->value)
            ->groupBy('savings_account_id')
            ->select('savings_account_id')
            ->selectRaw("SUM(CASE WHEN type = 'deposit' THEN amount ELSE -amount END) as balance")
            ->get()
            ->keyBy('savings_account_id');
        $savingsDiscrepancies = SavingsAccount::query()->get()
            ->filter(fn (SavingsAccount $account): bool => Money::cents(
                $expectedBalances->get($account->id)?->balance ?? '0.00',
            ) !== Money::cents($account->current_balance))
            ->count();

        return [
            'unbalanced_journals' => $unbalancedJournals,
            'savings_discrepancies' => $savingsDiscrepancies,
        ];
    }
}