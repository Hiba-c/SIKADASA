<?php

namespace App\Enums;

enum SavingsPayoutStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
}
