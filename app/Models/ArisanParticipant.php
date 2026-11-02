<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArisanParticipant extends Model
{
    protected $fillable = ['cycle_id', 'member_id', 'has_won', 'won_at'];

    protected function casts(): array
    {
        return ['has_won' => 'boolean', 'won_at' => 'datetime'];
    }

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(ArisanCycle::class, 'cycle_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(ArisanPayment::class, 'participant_id');
    }
}