<?php

namespace App\Policies;

use App\Models\SavingsAccount;
use App\Models\User;

class SavingsPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, SavingsAccount $account): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function transact(User $user, SavingsAccount $account): bool
    {
        return true;
    }

    public function delete(User $user, SavingsAccount $account): bool
    {
        return false;
    }
}