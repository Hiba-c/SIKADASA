<?php

namespace App\Enums;

enum ArisanRoundStatus: string
{
    case Open = 'open';
    case Drawn = 'drawn';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}