<?php

namespace App\Enums;

enum CommitteeDecision: string
{
    case Approved = 'APPROVED';
    case Amended = 'AMENDED';
    case Adjourned = 'ADJOURNED';
    case VerificationRequired = 'VERIFICATION_REQUIRED';
    case Rejected = 'REJECTED';
}
