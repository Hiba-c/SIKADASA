<?php

namespace App\Policies;

use App\Models\CashTransaction;
use App\Models\User;

class CashPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CashTransaction $transaction): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function reverse(User $user, CashTransaction $transaction): bool
    {
        return $user->isKetua();
    }

    public function delete(User $user, CashTransaction $transaction): bool
    {
        return false;
    }
}