<?php

namespace App\Enums;

enum FieldVisitStatus: string
{
    case Scheduled = 'SCHEDULED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case NoShow = 'NO_SHOW';
}
