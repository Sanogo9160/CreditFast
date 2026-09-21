<?php

namespace App\Enums;

enum ClientType: string
{
    case PhysicalPerson = 'PHYSICAL_PERSON';
    case LegalEntity = 'LEGAL_ENTITY';
}
