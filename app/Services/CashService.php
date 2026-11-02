<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Enums\TransactionType;
use App\Models\CashCategory;
use App\Models\CashTransaction;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashService
{
    public function __construct(
        private readonly SequenceService $sequences,
        private readonly JournalService $journals,
        private readonly AuditService $audit,
    ) {}

    public function create(array $data, int $userId): CashTransaction
    {
        return DB::transaction(function () use ($data, $userId): CashTransaction {
            $category = CashCategory::query()->lockForUpdate()->findOrFail($data['category_id']);
            $type = TransactionType::from($data['transaction_type']);

            if (! $category->is_active || $category->type !== $type) {
                throw ValidationException::withMessages([
                    'category_id' => 'Kategori tidak aktif atau tidak sesuai jenis transaksi.',
                ]);
            }

            $amount = $this->toCents($data['amount']);
            $transaction = CashTransaction::query()->create([
                ...$data,
                'transaction_number' => $this->sequences->next('KAS'),
                'transaction_type' => $type,
                'status' => TransactionStatus::Posted,
                'created_by' => $userId,
            ]);

            $cashLine = ['account_code' => '1001', 'debit' => 0, 'credit' => 0];
            $otherLine = ['account_code' => '5001', 'debit' => 0, 'credit' => 0];
            if ($type === TransactionType::Income) {
                $cashLine['debit'] = $amount;
                $otherLine['account_code'] = $category->name === 'Pendapatan jasa pinjaman' ? '4001' : '4002';
                $otherLine['credit'] = $amount;
            } else {
                $cashLine['credit'] = $amount;
                $otherLine['debit'] = $amount;
            }

            $this->journals->post(
                $transaction->transaction_number,
                $transaction->transaction_date->toDateString(),
                $transaction->description ?: $category->name,
                $transaction,
                $userId,
                [$cashLine, $otherLine],
            );
            $this->audit->record('cash', 'created', $transaction, null, $transaction->only([
                'transaction_number', 'category_id', 'transaction_type', 'payer_name', 'transaction_date', 'amount',
            ]));

            return $transaction->load('category');
        });
    }

    public function reverse(CashTransaction $transaction, int $userId, string $reason): CashTransaction
    {
        return DB::transaction(function () use ($transaction, $userId, $reason): CashTransaction {
            $locked = CashTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status !== TransactionStatus::Posted) {
                throw ValidationException::withMessages(['reversal_reason' => 'Transaksi ini sudah dibatalkan.']);
            }

            $locked->update([
                'status' => TransactionStatus::Reversed,
                'reversed_by' => $userId,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
            ]);
            $this->journals->reverse($locked, $userId, $reason);
            $this->audit->record('cash', 'reversed', $locked, ['status' => 'posted'], [
                'status' => 'reversed', 'reversal_reason' => $reason,
            ]);

            return $locked->fresh('category');
        });
    }

    private function toCents(string|int|float $amount): int
    {
        return Money::cents($amount);
    }
}