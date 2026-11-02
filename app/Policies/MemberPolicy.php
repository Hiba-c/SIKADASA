<?php

namespace App\Policies;

use App\Models\Member;
use App\Models\User;

class MemberPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Member $member): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Member $member): bool
    {
        return true;
    }

    public function changeStatus(User $user, Member $member): bool
    {
        return $user->isKetua();
    }

    public function delete(User $user, Member $member): bool
    {
        return false;
    }
}