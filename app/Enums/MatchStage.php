<?php

namespace App\Enums;

enum MatchStage: string
{
    case GROUP = 'group';
    case KNOCKOUT = 'knockout';
    case THIRD_PLACE = 'third_place';
    case FINAL = 'final';
}
