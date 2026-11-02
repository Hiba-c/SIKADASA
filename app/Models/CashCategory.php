<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashCategory extends Model
{
    protected $fillable = ['name', 'type', 'description', 'is_active'];

    protected function casts(): array
    {
        return ['type' => TransactionType::class, 'is_active' => 'boolean'];
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CashTransaction::class, 'category_id');
    }
}