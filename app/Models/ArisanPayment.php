<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArisanPayment extends Model
{
    protected $fillable = [
        'round_id', 'participant_id', 'transaction_number', 'amount',
        'payment_date', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payment_date' => 'date'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ArisanRound::class, 'round_id');
    }

    public function participant(): BelongsTo
    {
        return $this->belongsTo(ArisanParticipant::class, 'participant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}