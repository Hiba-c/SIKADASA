<?php

namespace App\Services;

use App\Models\TransactionSequence;
use Illuminate\Support\Facades\DB;

class SequenceService
{
    public function nextMemberCode(): string
    {
        return $this->next('DWS');
    }

    public function next(string $prefix): string
    {
        $period = now()->format('Ym');

        return DB::transaction(function () use ($prefix, $period): string {
            DB::table('transaction_sequences')->insertOrIgnore([
                'prefix' => $prefix,
                'period' => $period,
                'last_number' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = TransactionSequence::query()
                ->where('prefix', $prefix)
                ->where('period', $period)
                ->lockForUpdate()
                ->firstOrFail();

            $sequence->increment('last_number');

            return sprintf('%s-%s-%03d', $prefix, $period, $sequence->last_number);
        });
    }
}