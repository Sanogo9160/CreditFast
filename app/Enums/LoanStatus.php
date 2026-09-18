<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Approved = 'APPROVED';
    case Active = 'ACTIVE';
    case Closed = 'CLOSED';
    case Defaulted = 'DEFAULTED';
}
