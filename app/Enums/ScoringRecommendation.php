<?php

namespace App\Enums;

enum ScoringRecommendation: string
{
    case Favorable = 'FAVORABLE';
    case Reserved = 'RESERVED';
    case Unfavorable = 'UNFAVORABLE';
}
