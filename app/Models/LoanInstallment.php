<?php

namespace App\Models;

use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoanInstallment extends Model
{
    protected $fillable = [
        'loan_id', 'installment_number', 'due_date', 'principal_due',
        'service_due', 'principal_paid', 'service_paid', 'status',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'principal_due' => 'decimal:2',
            'service_due' => 'decimal:2',
            'principal_paid' => 'decimal:2',
            'service_paid' => 'decimal:2',
            'status' => InstallmentStatus::class,
        ];
    }

    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }
}