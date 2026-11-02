<?php

namespace App\Services;

use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Models\Loan;
use App\Models\Member;
use App\Models\OrganizationSetting;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LoanService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly JournalService $journals,
        private readonly AuditService $audit,
    ) {}

    public function serviceRate(): string
    {
        return (string) (OrganizationSetting::query()->value('loan_service_rate') ?? '0.50');
    }

    public function apply(array $data, int $userId): Loan
    {
        return DB::transaction(function () use ($data, $userId): Loan {
            $member = Member::query()->lockForUpdate()->findOrFail($data['member_id']);
            if ($member->status !== MemberStatus::Active) {
                throw ValidationException::withMessages(['member_id' => 'Pinjaman hanya dapat diajukan untuk anggota aktif.']);
            }

            $principal = Money::cents($data['principal_amount']);
            $rate = $this->serviceRate();
            $service = Money::percentageOf($principal, $rate);
            $loan = Loan::query()->create([
                'loan_number' => $this->sequences->next('PJM'),
                'member_id' => $member->id,
                'principal_amount' => Money::decimal($principal),
                'service_rate' => $rate,
                'service_amount' => Money::decimal($service),
                'total_obligation' => Money::decimal($principal + $service),
                'installment_count' => $data['installment_count'],
                'application_date' => $data['application_date'],
                'status' => LoanStatus::Pending,
                'created_by' => $userId,
            ]);
            $this->audit->record('loans', 'applied', $loan, null, [
                'member_id' => $member->id,
                'principal_amount' => $loan->principal_amount,
                'installment_count' => $loan->installment_count,
            ]);

            return $loan->load('member');
        });
    }

    public function approve(Loan $loan, int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $userId): Loan {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($locked->status !== LoanStatus::Pending) {
                throw ValidationException::withMessages(['loan' => 'Hanya pengajuan menunggu yang dapat disetujui.']);
            }
            $locked->update([
                'status' => LoanStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $userId,
            ]);
            $this->audit->record('loans', 'approved', $locked, ['status' => 'pending'], ['status' => 'approved']);

            return $locked->fresh('member');
        });
    }

    public function reject(Loan $loan): Loan
    {
        return DB::transaction(function () use ($loan): Loan {
            $locked = Loan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($locked->status !== LoanStatus::Pending) {
                throw ValidationException::withMessages(['loan' => 'Hanya pengajuan menunggu yang dapat ditolak.']);
            }
            $locked->update(['status' => LoanStatus::Rejected]);
            $this->audit->record('loans', 'rejected', $locked, ['status' => 'pending'], ['status' => 'rejected']);

            return $locked->fresh('member');
        });
    }

    public function disburse(Loan $loan, string $date, int $userId): Loan
    {
        return DB::transaction(function () use ($loan, $date, $userId): Loan {
            $locked = Loan::query()->lockForUpdate()->with('member')->findOrFail($loan->id);
            if ($locked->status !== LoanStatus::Approved) {
                throw ValidationException::withMessages(['loan' => 'Hanya pinjaman yang disetujui yang dapat dicairkan.']);
            }
            if ($locked->member->status !== MemberStatus::Active) {
                throw ValidationException::withMessages(['loan' => 'Anggota tidak aktif; pinjaman tidak dapat dicairkan.']);
            }
            if ($locked->installments()->exists()) {
                throw ValidationException::withMessages(['loan' => 'Jadwal angsuran sudah pernah dibuat.']);
            }

            $principal = Money::cents($locked->principal_amount);
            $service = Money::cents($locked->service_amount);
            $principalParts = $this->split($principal, $locked->installment_count);
            $serviceParts = $this->split($service, $locked->installment_count);
            $firstDueDate = CarbonImmutable::parse($date)->addMonthNoOverflow();
            foreach ($principalParts as $index => $principalDue) {
                $locked->installments()->create([
                    'installment_number' => $index + 1,
                    'due_date' => $firstDueDate->addMonthsNoOverflow($index)->toDateString(),
                    'principal_due' => Money::decimal($principalDue),
                    'service_due' => Money::decimal($serviceParts[$index]),
                    'status' => 'unpaid',
                ]);
            }
            $locked->update(['status' => LoanStatus::Active, 'disbursed_at' => $date]);
            $number = $this->sequences->next('CAI');
            $this->journals->post(
                $number,
                $date,
                'Pencairan pinjaman '.$locked->loan_number.' untuk '.$locked->member->full_name,
                $locked,
                $userId,
                [
                    ['account_code' => '1101', 'debit' => $principal, 'credit' => 0],
                    ['account_code' => '1001', 'debit' => 0, 'credit' => $principal],
                ],
            );
            $this->audit->record('loans', 'disbursed', $locked, ['status' => 'approved'], [
                'status' => 'active', 'disbursed_at' => $date,
            ]);

            return $locked->fresh('member', 'installments');
        });
    }

    private function split(int $amount, int $parts): array
    {
        $base = intdiv($amount, $parts);
        $remainder = $amount % $parts;

        return array_map(fn (int $index): int => $base + ($index < $remainder ? 1 : 0), range(0, $parts - 1));
    }

}