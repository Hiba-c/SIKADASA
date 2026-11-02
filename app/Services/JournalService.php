<?php

namespace App\Services;

use App\Models\Account;
use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class JournalService
{
    /**
     * @param  array<int, array{account_code: string, debit: int, credit: int, description?: string|null}>  $lines
     */
    public function post(
        string $number,
        string $date,
        string $description,
        Model $reference,
        int $userId,
        array $lines,
    ): JournalEntry {
        $debits = array_sum(array_column($lines, 'debit'));
        $credits = array_sum(array_column($lines, 'credit'));

        if ($debits <= 0 || $debits !== $credits) {
            throw ValidationException::withMessages([
                'journal' => 'Jurnal tidak seimbang; transaksi tidak dapat disimpan.',
            ]);
        }

        $entry = JournalEntry::query()->create([
            'transaction_number' => $number,
            'transaction_date' => $date,
            'description' => $description,
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
            'status' => 'posted',
            'created_by' => $userId,
            'posted_at' => now(),
        ]);

        foreach ($lines as $line) {
            $account = Account::query()
                ->where('account_code', $line['account_code'])
                ->where('is_active', true)
                ->first();

            if ($account === null) {
                throw new \LogicException("Active chart-of-account code {$line['account_code']} is missing.");
            }

            $entry->lines()->create([
                'account_id' => $account->id,
                'debit' => Money::decimal($line['debit']),
                'credit' => Money::decimal($line['credit']),
                'description' => $line['description'] ?? null,
            ]);
        }

        return $entry;
    }

    public function reverse(Model $reference, int $userId, string $reason): void
    {
        $entry = JournalEntry::query()
            ->where('reference_type', $reference::class)
            ->where('reference_id', $reference->getKey())
            ->where('status', 'posted')
            ->with('lines')
            ->first();

        if ($entry === null) {
            throw new \LogicException('Cannot reverse a financial transaction without its posted journal entry.');
        }

        $entry->update(['status' => 'reversed']);
        $reversal = JournalEntry::query()->create([
            'transaction_number' => $entry->transaction_number.'-REV',
            'transaction_date' => now()->toDateString(),
            'description' => 'Pembalikan: '.$reason,
            'reference_type' => $reference::class,
            'reference_id' => $reference->getKey(),
            'status' => 'posted',
            'created_by' => $userId,
            'posted_at' => now(),
        ]);

        foreach ($entry->lines as $line) {
            $reversal->lines()->create([
                'account_id' => $line->account_id,
                'debit' => $line->credit,
                'credit' => $line->debit,
                'description' => 'Pembalikan '.$entry->transaction_number,
            ]);
        }
    }
}