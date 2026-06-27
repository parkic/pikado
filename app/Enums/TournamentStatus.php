<?php

namespace App\Enums;

enum TournamentStatus: string
{
    case DRAFT = 'draft';
    case GROUP_DRAW = 'group_draw';
    case READY = 'ready';
    case GROUP_STAGE = 'group_stage';
    case REPECHAGE = 'repechage';
    case KNOCKOUT_DRAW = 'knockout_draw';
    case KNOCKOUT_STAGE = 'knockout_stage';
    case FINISHED = 'finished';
}
