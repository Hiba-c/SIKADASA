<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavingsTransaction extends Model
{
    protected $fillable = [
        'transaction_number', 'savings_account_id', 'type', 'amount',
        'transaction_date', 'description', 'status', 'created_by',
        'reversed_by', 'reversed_at', 'reversal_reason', 'savings_payout_item_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'transaction_date' => 'date',
            'status' => TransactionStatus::class,
            'reversed_at' => 'datetime',
        ];
    }

    public function savingsAccount(): BelongsTo
    {
        return $this->belongsTo(SavingsAccount::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function savingsPayoutItem(): BelongsTo
    {
        return $this->belongsTo(SavingsPayoutItem::class);
    }
}
