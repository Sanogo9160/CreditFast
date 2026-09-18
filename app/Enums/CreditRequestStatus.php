<?php

namespace App\Enums;

enum CreditRequestStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Analysis = 'ANALYSIS';
    case VerificationRequired = 'VERIFICATION_REQUIRED';
    case CreditReview = 'CREDIT_REVIEW';
    case Committee = 'COMMITTEE';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';
    case Disbursed = 'DISBURSED';
}
