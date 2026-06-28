<?php

namespace App\Enums;

enum QualificationStatus: string
{
    case DIRECT = 'direct';
    case REPECHAGE = 'repechage';
    case ELIMINATED = 'eliminated';
}
