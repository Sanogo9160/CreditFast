<?php

namespace App\Enums;

enum FieldVisitOutcome: string
{
    case Favorable = 'FAVORABLE';
    case Reserved = 'RESERVED';
    case Unfavorable = 'UNFAVORABLE';
}
