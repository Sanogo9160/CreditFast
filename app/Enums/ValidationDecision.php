<?php

namespace App\Enums;

enum ValidationDecision: string
{
    case Validated = 'VALIDATED';
    case ToComplete = 'TO_COMPLETE';
    case Rejected = 'REJECTED';
}
