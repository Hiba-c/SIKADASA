<?php

namespace App\Models;

use App\Enums\ArisanCycleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArisanCycle extends Model
{
    protected $fillable = [
        'cycle_number', 'name', 'contribution_amount', 'status',
        'started_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'contribution_amount' => 'decimal:2',
            'status' => ArisanCycleStatus::class,
            'started_at' => 'date',
            'completed_at' => 'date',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ArisanParticipant::class, 'cycle_id');
    }

    public function rounds(): HasMany
    {
        return $this->hasMany(ArisanRound::class, 'cycle_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}