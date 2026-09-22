<?php

namespace App\Enums;

enum BankAccountApplicationStatus: string
{
    case Draft = 'DRAFT';
    case Submitted = 'SUBMITTED';
    case Returned = 'RETURNED';
    case Approved = 'APPROVED';
    case Rejected = 'REJECTED';

    public function isEditableByClient(): bool
    {
        return in_array($this, [self::Draft, self::Returned], true);
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Draft, self::Submitted, self::Returned], true);
    }
}
