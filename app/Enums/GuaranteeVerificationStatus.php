<?php

namespace App\Enums;

enum GuaranteeVerificationStatus: string
{
    case Pending = 'PENDING';
    case Verified = 'VERIFIED';
    case Rejected = 'REJECTED';
}
