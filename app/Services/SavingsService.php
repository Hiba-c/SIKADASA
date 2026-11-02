<?php

namespace App\Services;

use App\Enums\MemberStatus;
use App\Enums\TransactionStatus;
use App\Models\SavingsAccount;
use App\Models\SavingsTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SavingsService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly JournalService $journals,
        private readonly AuditService $audit,
    ) {}

    public function transact(SavingsAccount $account, array $data, int $userId): SavingsTransaction
    {
        return DB::transaction(function () use ($account, $data, $userId): SavingsTransaction {
            $locked = SavingsAccount::query()->lockForUpdate()->with('member')->findOrFail($account->id);
            if ($locked->member->status !== MemberStatus::Active || $locked->status !== 'active') {
                throw ValidationException::withMessages(['member' => 'Transaksi hanya dapat dilakukan untuk anggota aktif.']);
            }

            $amount = $this->toCents($data['amount']);
            $balance = $this->toCents($locked->current_balance);
            $type = $data['type'];
            if ($type === 'withdrawal' && $amount > $balance) {
                throw ValidationException::withMessages(['amount' => 'Saldo tabungan tidak mencukupi.']);
            }

            $newBalance = $type === 'deposit' ? $balance + $amount : $balance - $amount;
            $transaction = SavingsTransaction::query()->create([
                ...$data,
                'transaction_number' => $this->sequences->next('SMP'),
                'savings_account_id' => $locked->id,
                'status' => TransactionStatus::Posted,
                'created_by' => $userId,
            ]);
            $locked->update(['current_balance' => $this->fromCents($newBalance)]);

            $this->journals->post(
                $transaction->transaction_number,
                $transaction->transaction_date->toDateString(),
                $transaction->description ?: ucfirst($type).' tabungan '.$locked->member->full_name,
                $transaction,
                $userId,
                $type === 'deposit'
                    ? [
                        ['account_code' => '1001', 'debit' => $amount, 'credit' => 0],
                        ['account_code' => '2001', 'debit' => 0, 'credit' => $amount],
                    ]
                    : [
                        ['account_code' => '2001', 'debit' => $amount, 'credit' => 0],
                        ['account_code' => '1001', 'debit' => 0, 'credit' => $amount],
                    ],
            );
            $this->audit->record('savings', $type, $transaction, null, [
                'member_id' => $locked->member_id,
                'amount' => $transaction->amount,
                'balance' => $this->fromCents($newBalance),
                'savings_payout_item_id' => $transaction->savings_payout_item_id,
            ]);

            return $transaction->load('savingsAccount.member');
        });
    }

    private function toCents(string|int|float $amount): int
    {
        return Money::cents($amount);
    }

    private function fromCents(int $amount): string
    {
        return Money::decimal($amount);
    }
}
