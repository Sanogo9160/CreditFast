<?php

namespace App\Support;

use App\Enums\ClientType;

final class InstitutionalAccountRequirement
{
    public const ERROR_FIELD = 'client';

    public static function message(?ClientType $clientType = null): string
    {
        $endpoint = $clientType === ClientType::LegalEntity
            ? '/api/bank-account-applications/legal-entity'
            : '/api/bank-account-applications/physical-person';

        return 'Une demande de crédit nécessite un compte institutionnel. '
            ."Ouvrez un compte via POST {$endpoint} (fiche d’adhésion), puis réessayez.";
    }

    public static function submitBlockedMessage(): string
    {
        return 'La demande ne peut pas être transmise sans compte institutionnel. '
            .'Complétez d’abord une fiche d’adhésion (PP ou PM), puis réessayez.';
    }

    public static function scoringBlockedMessage(): string
    {
        return 'Scoring impossible : le client doit disposer d’un compte institutionnel '
            .'(adhésion approuvée ou historique IMF).';
    }
}
