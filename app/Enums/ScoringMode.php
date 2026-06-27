<?php

namespace App\Enums;

enum ScoringMode: string
{
    case POINTS_DIFFERENCE = 'points_difference';
    case WINNER_ONLY = 'winner_only';
}
