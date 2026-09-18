<?php

namespace App\Enums;

enum AnomalyStatus: string
{
    case Open = 'OPEN';
    case Resolved = 'RESOLVED';
    case Ignored = 'IGNORED';
}
