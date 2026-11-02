<?php

namespace App\Policies;

use App\Models\Loan;
use App\Models\User;

class LoanPolicy
{
    public function viewApprovals(User $user): bool
    {
        return $user->isKetua();
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Loan $loan): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function approve(User $user, Loan $loan): bool
    {
        return $user->isKetua();
    }

    public function reject(User $user, Loan $loan): bool
    {
        return $user->isKetua();
    }

    public function disburse(User $user, Loan $loan): bool
    {
        return true;
    }
}