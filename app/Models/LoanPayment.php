<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanPayment extends Model
{
    protected $fillable = [
        'payment_number', 'loan_id', 'payment_date', 'principal_amount',
        'service_amount', 'total_amount', 'status', 'received_by',
    ];

    protected function casts(): array
    {
        return [
            'payment_date' => 'date',
            'principal_amount' => 'decimal:2',
            'service_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'status' => TransactionStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }
}