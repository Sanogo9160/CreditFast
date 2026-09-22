<?php

namespace App\Enums;

enum LegalForm: string
{
    case Sarl = 'SARL';
    case Sa = 'SA';
    case Sas = 'SAS';
    case Sau = 'SAU';
    case Gie = 'GIE';
    case Scoop = 'SCOOP';
    case Sci = 'SCI';
    case Ei = 'EI';
    case Other = 'OTHER';

    public function label(): string
    {
        return match ($this) {
            self::Sarl => 'Société à responsabilité limitée',
            self::Sa => 'Société anonyme',
            self::Sas => 'Société par actions simplifiée',
            self::Sau => 'Société anonyme unipersonnelle',
            self::Gie => 'Groupement d’intérêt économique',
            self::Scoop => 'Société cooperative',
            self::Sci => 'Société civile immobilière',
            self::Ei => 'Entreprise individuelle',
            self::Other => 'Autre forme juridique',
        };
    }
}
