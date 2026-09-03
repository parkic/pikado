<?php

namespace App\Enums;

enum MatchStatus: string
{
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case POSTPONED = 'postponed';
    case FINISHED = 'finished';
    case VOIDED = 'voided';
    case CANCELLED = 'cancelled';
}
