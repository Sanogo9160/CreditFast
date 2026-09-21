<?php

namespace App\Enums;

enum FactorType: string
{
    case IncomeConsistency = 'income_consistency';
    case Expense = 'expense';
    case Activity = 'activity';
    case ActivityVitality = 'activity_vitality';
    case Document = 'document';
    case Savings = 'savings';
    case CreditHistory = 'credit_history';
    case Guarantee = 'guarantee';
    case RepaymentCapacity = 'repayment_capacity';
    case ResidentialZone = 'residential_zone';
}
