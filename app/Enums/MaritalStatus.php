<?php

namespace App\Enums;

enum MaritalStatus: string
{
    case Single = 'SINGLE';
    case Married = 'MARRIED';
    case Divorced = 'DIVORCED';
    case Widowed = 'WIDOWED';
    case Other = 'OTHER';
}
