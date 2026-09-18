<?php

namespace App\Enums;

enum CommitteeDecision: string
{
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Amended = 'AMENDED';
}
