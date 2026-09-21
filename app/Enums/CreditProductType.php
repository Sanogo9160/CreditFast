<?php

namespace App\Enums;

enum CreditProductType: string
{
    // Personne physique
    case Mortgage = 'MORTGAGE';
    case ConsumerAssigned = 'CONSUMER_ASSIGNED';
    case ConsumerPersonal = 'CONSUMER_PERSONAL';
    case Revolving = 'REVOLVING';
    case Student = 'STUDENT';
    case ProfessionalWorkingCapital = 'PROFESSIONAL_WORKING_CAPITAL';

    // Personne morale
    case Investment = 'INVESTMENT';
    case Overdraft = 'OVERDRAFT';
    case CashFacility = 'CASH_FACILITY';
    case Campaign = 'CAMPAIGN';
    case Discount = 'DISCOUNT';
    case Factoring = 'FACTORING';
    case Leasing = 'LEASING';

    public function clientType(): ClientType
    {
        return match ($this) {
            self::Mortgage,
            self::ConsumerAssigned,
            self::ConsumerPersonal,
            self::Revolving,
            self::Student,
            self::ProfessionalWorkingCapital => ClientType::PhysicalPerson,
            self::Investment,
            self::Overdraft,
            self::CashFacility,
            self::Campaign,
            self::Discount,
            self::Factoring,
            self::Leasing => ClientType::LegalEntity,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Mortgage => 'Crédit immobilier',
            self::ConsumerAssigned => 'Crédit à la consommation affecté',
            self::ConsumerPersonal => 'Prêt personnel non affecté',
            self::Revolving => 'Crédit renouvelable',
            self::Student => 'Prêt étudiant / scolaire',
            self::ProfessionalWorkingCapital => 'Crédit professionnel / fonds de roulement',
            self::Investment => 'Crédit d’investissement',
            self::Overdraft => 'Découvert bancaire',
            self::CashFacility => 'Facilité de caisse',
            self::Campaign => 'Crédit de campagne',
            self::Discount => 'Escompte commercial',
            self::Factoring => 'Affacturage',
            self::Leasing => 'Crédit-bail (leasing)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Mortgage => 'Achat de logement, terrain ou travaux de rénovation importants.',
            self::ConsumerAssigned => 'Financement lié à un bien précis (véhicule, équipement) sur justificatif d’achat.',
            self::ConsumerPersonal => 'Somme libre sans justificatif d’utilisation, pour un besoin de trésorerie personnelle.',
            self::Revolving => 'Réserve d’argent reconstituée au fil des remboursements.',
            self::Student => 'Frais de scolarité, matériel ou vie courante pendant les études.',
            self::ProfessionalWorkingCapital => 'Besoins d’activité d’un particulier commerçant / artisan (stock, cycle court).',
            self::Investment => 'Acquisition d’actifs durables : matériel, véhicules pro, immobilier d’entreprise, fonds de commerce.',
            self::Overdraft => 'Autorisation de solde négatif court pour un décalage de trésorerie.',
            self::CashFacility => 'Avance de fonds très courte (quelques jours), souvent en fin de mois.',
            self::Campaign => 'Financement saisonnier (agriculture, commerce avant pics d’activité).',
            self::Discount => 'Avance sur une créance client non échue (facture, effet de commerce).',
            self::Factoring => 'Cession de factures pour paiement immédiat et recouvrement délégué.',
            self::Leasing => 'Location de bien mobilier ou immobilier avec option d’achat en fin de contrat.',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::Mortgage => 'immobilier',
            self::ConsumerAssigned, self::ConsumerPersonal, self::Revolving, self::Student => 'consommation',
            self::ProfessionalWorkingCapital => 'professionnel',
            self::Investment, self::Leasing => 'investissement',
            self::Overdraft, self::CashFacility, self::Campaign => 'tresorerie',
            self::Discount, self::Factoring => 'poste_clients',
        };
    }

    public function isCompatibleWith(ClientType $clientType): bool
    {
        return $this->clientType() === $clientType;
    }

    /**
     * @return list<self>
     */
    public static function forClientType(ClientType $clientType): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $type): bool => $type->isCompatibleWith($clientType)
        ));
    }
}

