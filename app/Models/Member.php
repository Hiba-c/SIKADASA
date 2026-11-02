<?php

namespace App\Models;

use App\Enums\MemberStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'registration_code', 'full_name', 'address', 'house_number',
        'phone', 'joined_at', 'notes', 'status', 'inactive_at', 'inactive_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => 'date',
            'inactive_at' => 'datetime',
            'status' => MemberStatus::class,
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function savingsAccount(): HasOne
    {
        return $this->hasOne(SavingsAccount::class);
    }

    public function arisanParticipants(): HasMany
    {
        return $this->hasMany(ArisanParticipant::class);
    }

    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', MemberStatus::Active);
    }
}