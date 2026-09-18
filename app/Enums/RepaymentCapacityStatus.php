<?php

namespace App\Enums;

enum RepaymentCapacityStatus: string
{
    case Sufficient = 'SUFFICIENT';
    case Insufficient = 'INSUFFICIENT';
}
