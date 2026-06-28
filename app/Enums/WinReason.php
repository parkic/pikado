<?php

namespace App\Enums;

enum WinReason: string
{
    case NORMAL = 'normal';
    case WALKOVER = 'walkover';
    case OPPONENT_WITHDREW = 'opponent_withdrew';
    case MANUAL_OVERRIDE = 'manual_override';
}
