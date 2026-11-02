<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsPayoutItem extends Model
{
    protected $fillable = ['savings_payout_batch_id', 'savings_account_id', 'amount_due', 'amount_paid'];

    protected function casts(): array
    {
        return ['amount_due' => 'decimal:2', 'amount_paid' => 'decimal:2'];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(SavingsPayoutBatch::class, 'savings_payout_batch_id');
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(SavingsTransaction::class);
    }
}
