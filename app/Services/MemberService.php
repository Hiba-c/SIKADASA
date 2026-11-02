<?php

namespace App\Services;

use App\Enums\ArisanCycleStatus;
use App\Enums\ArisanRoundStatus;
use App\Enums\LoanStatus;
use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Models\Member;
use App\Models\SavingsAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly AuditService $audit,
    ) {}

    public function create(array $data, int $userId): Member
    {
        return DB::transaction(function () use ($data, $userId): Member {
            $member = Member::query()->create([
                ...$data,
                'registration_code' => $this->sequences->nextMemberCode(),
                'status' => MemberStatus::Active,
                'created_by' => $userId,
            ]);

            SavingsAccount::query()->create(['member_id' => $member->id]);
            $this->audit->record('members', 'created', $member, null, [
                'registration_code' => $member->registration_code,
                'full_name' => $member->full_name,
                'status' => $member->status->value,
            ]);

            return $member;
        });
    }

    public function update(Member $member, array $data): Member
    {
        return DB::transaction(function () use ($member, $data): Member {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $changedFields = array_keys(array_diff_assoc(
                $data,
                array_intersect_key($lockedMember->getAttributes(), $data),
            ));
            $lockedMember->update($data);
            if ($changedFields !== []) {
                $this->audit->record('members', 'updated', $lockedMember, null, [
                    'changed_fields' => $changedFields,
                ]);
            }

            return $lockedMember->refresh();
        });
    }

    /**
     * Summarize unresolved financial obligations before a chairperson deactivates a member.
     *
     * @return array{savings_balance: string, loan_principal: string, loan_service: string, arisan_contributions: string, unpaid_arisan_rounds: int, can_deactivate: bool}
     */
    public function deactivationCheck(Member $member): array
    {
        $savingsBalance = $this->toCents($member->savingsAccount?->current_balance ?? '0.00');
        $loanPrincipal = 0;
        $loanService = 0;

        $loans = $member->loans()
            ->where('status', LoanStatus::Active)
            ->with([
                'installments',
                'payments' => fn ($query) => $query->where('status', TransactionStatus::Posted),
            ])
            ->get();

        foreach ($loans as $loan) {
            if ($loan->installments->isNotEmpty()) {
                foreach ($loan->installments as $installment) {
                    $loanPrincipal += max(
                        0,
                        $this->toCents($installment->principal_due) - $this->toCents($installment->principal_paid),
                    );
                    $loanService += max(
                        0,
                        $this->toCents($installment->service_due) - $this->toCents($installment->service_paid),
                    );
                }

                continue;
            }

            $paidPrincipal = $loan->payments->sum(fn ($payment) => $this->toCents($payment->principal_amount));
            $paidService = $loan->payments->sum(fn ($payment) => $this->toCents($payment->service_amount));
            $loanPrincipal += max(0, $this->toCents($loan->principal_amount) - $paidPrincipal);
            $loanService += max(0, $this->toCents($loan->service_amount) - $paidService);
        }

        $arisan = DB::table('arisan_rounds')
            ->join('arisan_cycles', 'arisan_cycles.id', '=', 'arisan_rounds.cycle_id')
            ->join('arisan_participants', 'arisan_participants.cycle_id', '=', 'arisan_cycles.id')
            ->leftJoin('arisan_payments', function ($join): void {
                $join->on('arisan_payments.round_id', '=', 'arisan_rounds.id')
                    ->on('arisan_payments.participant_id', '=', 'arisan_participants.id')
                    ->where('arisan_payments.status', '=', TransactionStatus::Posted->value);
            })
            ->where('arisan_participants.member_id', $member->id)
            ->where('arisan_cycles.status', ArisanCycleStatus::Active->value)
            ->whereIn('arisan_rounds.status', [
                ArisanRoundStatus::Open->value,
                ArisanRoundStatus::Drawn->value,
            ])
            ->whereNull('arisan_payments.id')
            ->selectRaw('COUNT(*) as unpaid_rounds, COALESCE(SUM(arisan_cycles.contribution_amount), 0) as contribution_total')
            ->first();

        $arisanContributions = $this->toCents($arisan->contribution_total ?? '0.00');

        return [
            'savings_balance' => $this->fromCents($savingsBalance),
            'loan_principal' => $this->fromCents($loanPrincipal),
            'loan_service' => $this->fromCents($loanService),
            'arisan_contributions' => $this->fromCents($arisanContributions),
            'unpaid_arisan_rounds' => (int) ($arisan->unpaid_rounds ?? 0),
            'can_deactivate' => $savingsBalance === 0
                && $loanPrincipal === 0
                && $loanService === 0
                && $arisanContributions === 0,
        ];
    }

    public function deactivate(Member $member, string $reason): Member
    {
        return DB::transaction(function () use ($member, $reason): Member {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $check = $this->deactivationCheck($lockedMember);

            if (! $check['can_deactivate']) {
                throw ValidationException::withMessages([
                    'deactivation' => 'Selesaikan saldo tabungan, pinjaman, dan iuran arisan yang tercantum sebelum menonaktifkan anggota.',
                ]);
            }

            $lockedMember->update([
                'status' => MemberStatus::Inactive,
                'inactive_at' => now(),
                'inactive_reason' => $reason,
            ]);
            $this->audit->record('members', 'deactivated', $lockedMember, ['status' => MemberStatus::Active->value], [
                'status' => MemberStatus::Inactive->value,
                'inactive_reason' => $reason,
            ]);

            return $lockedMember->refresh();
        });
    }

    public function activate(Member $member): Member
    {
        return DB::transaction(function () use ($member): Member {
            $lockedMember = Member::query()->lockForUpdate()->findOrFail($member->id);
            $lockedMember->update([
                'status' => MemberStatus::Active,
                'inactive_at' => null,
                'inactive_reason' => null,
            ]);
            $this->audit->record('members', 'activated', $lockedMember, ['status' => MemberStatus::Inactive->value], [
                'status' => MemberStatus::Active->value,
            ]);

            return $lockedMember->refresh();
        });
    }

    private function toCents(string|int|float|null $amount): int
    {
        $amount = (string) ($amount ?? '0');

        if (! preg_match('/^(-?)(\d+)(?:\.(\d{1,2}))?$/', $amount, $parts)) {
            throw new \InvalidArgumentException('Invalid monetary value in member obligation check.');
        }

        $cents = ((int) $parts[2] * 100) + (int) str_pad($parts[3] ?? '', 2, '0');

        return ($parts[1] ?? '') === '-' ? -$cents : $cents;
    }

    private function fromCents(int $cents): string
    {
        return sprintf('%d.%02d', intdiv($cents, 100), abs($cents % 100));
    }
}