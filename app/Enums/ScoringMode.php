<?php

namespace App\Enums;

enum ScoringMode: string
{
    case Standard = 'STANDARD';
    case ColdStart = 'COLD_START';
}
