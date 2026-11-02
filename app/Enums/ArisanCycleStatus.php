<?php

namespace App\Enums;

enum ArisanCycleStatus: string
{
    case Active = 'active';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}