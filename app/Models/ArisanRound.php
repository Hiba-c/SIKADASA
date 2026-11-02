<?php

namespace App\Models;

use App\Enums\ArisanRoundStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArisanRound extends Model
{
    protected $fillable = [
        'cycle_id', 'round_number', 'round_date', 'status',
        'winner_participant_id', 'total_collected', 'drawn_at', 'drawn_by',
    ];

    protected function casts(): array
    {
        return [
            'round_date' => 'date',
            'status' => ArisanRoundStatus::class,
            'total_collected' => 'decimal:2',
            'drawn_at' => 'datetime',
        ];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ArisanCycle::class, 'cycle_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(ArisanParticipant::class, 'winner_participant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ArisanPayment::class, 'round_id');
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(ArisanPayout::class, 'round_id');
    }

    public function drawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'drawn_by');
    }
}