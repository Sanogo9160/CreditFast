<?php

namespace App\Enums;

enum RoleName: string
{
    case Admin = 'admin';
    case CreditAgent = 'credit_agent';
    case Analyst = 'analyst';
    case CommitteeMember = 'committee_member';
    case Client = 'client';
}
