<?php

namespace App\Policies;

use App\Models\User;

class ReportPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function export(User $user): bool
    {
        return true;
    }

    public function validate(User $user): bool
    {
        return $user->isKetua();
    }

}