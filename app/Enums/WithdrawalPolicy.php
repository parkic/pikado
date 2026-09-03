<?php

namespace App\Enums;

enum WithdrawalPolicy: string
{
    case VOID_ALL = 'void_all';
    case KEEP_PLAYED_AVERAGE_REST = 'keep_played_average_rest';
    case KEEP_PLAYED_MANUAL_REST = 'keep_played_manual_rest';
}
