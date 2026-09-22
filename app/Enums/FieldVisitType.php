<?php

namespace App\Enums;

enum FieldVisitType: string
{
    case ActivitySite = 'ACTIVITY_SITE';
    case Residence = 'RESIDENCE';
    case GuaranteeAsset = 'GUARANTEE_ASSET';
    case Other = 'OTHER';
}
