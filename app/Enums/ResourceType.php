<?php

namespace App\Enums;

enum ResourceType: string
{
    case DART_BOARD = 'dart_board';
    case BEER_PONG_TABLE = 'beer_pong_table';
    case OTHER = 'other';
}
