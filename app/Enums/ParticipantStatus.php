<?php

namespace App\Enums;

enum ParticipantStatus: string
{
    case ACTIVE = 'active';
    case WITHDRAWN = 'withdrawn';
}
