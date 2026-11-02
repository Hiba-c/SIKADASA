<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TransactionSequence extends Model
{
    protected $fillable = ['prefix', 'period', 'last_number'];

    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}