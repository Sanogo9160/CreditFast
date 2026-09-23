<?php

namespace App\Enums;

enum CreditRequestStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Received = 'RECEIVED';
    case UnderReview = 'UNDER_REVIEW';
    case VerificationRequired = 'VERIFICATION_REQUIRED';
    case InAnalysis = 'IN_ANALYSIS';
    case PendingAnalysis = 'PENDING_ANALYSIS';
    case PendingCommittee = 'PENDING_COMMITTEE';
    case Committee = 'COMMITTEE';
    case Approved = 'APPROVED';
    case Amended = 'AMENDED';
    case Rejected = 'REJECTED';
    case Adjourned = 'ADJOURNED';

    /**
     * @return list<self>
     */
    public static function agentOwned(): array
    {
        return [
            self::Submitted,
            self::Received,
            self::UnderReview,
            self::VerificationRequired,
        ];
    }

    /**
     * @return list<self>
     */
    public static function analystOwned(): array
    {
        return [
            self::InAnalysis,
            self::PendingAnalysis,
        ];
    }

    /**
     * @return list<self>
     */
    public static function committeeOwned(): array
    {
        return [
            self::PendingCommittee,
            self::Committee,
        ];
    }

    /**
     * @return list<self>
     */
    public static function closed(): array
    {
        return [
            self::Approved,
            self::Amended,
            self::Rejected,
            self::Adjourned,
        ];
    }

    public function isClosed(): bool
    {
        return in_array($this, self::closed(), true);
    }
}
