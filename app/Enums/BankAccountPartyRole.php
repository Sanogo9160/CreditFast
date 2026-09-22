<?php

namespace App\Enums;

enum BankAccountPartyRole: string
{
    case Signatory = 'SIGNATORY';
    case BeneficialOwner = 'BENEFICIAL_OWNER';
}
