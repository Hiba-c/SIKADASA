<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArisanPayout extends Model
{
    protected $fillable = [
        'round_id', 'winner_participant_id', 'payout_number', 'amount',
        'payout_date', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'payout_date' => 'date'];
    }

    public function round(): BelongsTo
    {
        return $this->belongsTo(ArisanRound::class, 'round_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ArisanParticipant::class, 'winner_participant_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}