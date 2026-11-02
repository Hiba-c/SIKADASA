<?php

namespace App\Policies;

use App\Models\ArisanCycle;
use App\Models\User;

class ArisanPolicy
{
    public function create(User $user): bool
    {
        return $user->isKetua();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ArisanCycle $cycle): bool
    {
        return true;
    }

    public function manageCycle(User $user, ArisanCycle $cycle): bool
    {
        return $user->isKetua();
    }

    public function draw(User $user, ArisanCycle $cycle): bool
    {
        return $user->isKetua();
    }
}