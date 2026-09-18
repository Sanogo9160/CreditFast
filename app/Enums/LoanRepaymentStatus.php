<?php

namespace App\Enums;

enum LoanRepaymentStatus: string
{
    case Pending = 'PENDING';
    case Paid = 'PAID';
    case Late = 'LATE';
}
