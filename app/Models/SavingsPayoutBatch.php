<?php

namespace App\Models;

use App\Enums\SavingsPayoutStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SavingsPayoutBatch extends Model
{
    protected $fillable = [
        'hijri_year', 'payout_date', 'status', 'total_due', 'total_paid',
        'created_by', 'approved_by', 'approved_at', 'last_processed_by', 'last_processed_at',
    ];

    protected function casts(): array
    {
        return [
            'payout_date' => 'date',
            'total_due' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'status' => SavingsPayoutStatus::class,
            'approved_at' => 'datetime',
            'last_processed_at' => 'datetime',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(SavingsPayoutItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lastProcessedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_processed_by');
    }
}
