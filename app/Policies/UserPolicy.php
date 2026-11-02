<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isKetua();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isKetua() || $user->is($target);
    }

    public function create(User $user): bool
    {
        return $user->isKetua();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isKetua() && ! $user->is($target);
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isKetua() && ! $user->is($target);
    }
}