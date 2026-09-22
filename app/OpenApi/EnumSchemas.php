<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'RoleName',
    type: 'string',
    description: <<<'MD'
Rôle de l’utilisateur (`roles.name`).

- `client` — demandeur
- `credit_agent` — chargé de crédit
- `analyst` — analyste
- `committee_member` — membre du comité
- `admin` — administrateur
MD,
    enum: ['client', 'credit_agent', 'analyst', 'committee_member', 'admin'],
    example: 'client'
)]
#[OA\Schema(
    schema: 'StaffRoleName',
    type: 'string',
    description: <<<'MD'
Rôle d’un compte interne. Un client ne se crée pas ici.

- `credit_agent` — chargé de crédit
- `analyst` — analyste
- `committee_member` — membre du comité
- `admin` — administrateur
MD,
    enum: ['admin', 'credit_agent', 'analyst', 'committee_member'],
    example: 'credit_agent'
)]
#[OA\Schema(
    schema: 'UserStatus',
    type: 'string',
    description: <<<'MD'
État du compte utilisateur.

- `active` — peut se connecter
- `inactive` — connexion refusée
MD,
    enum: ['active', 'inactive'],
    example: 'active'
)]
#[OA\Schema(
    schema: 'CreditRequestStatus',
    type: 'string',
    description: <<<'MD'
État du dossier de crédit.

- `DRAFT` — brouillon, encore chez le client, pas transmis au chargé
- `SUBMITTED` — transmis à l’équipe
- `ANALYSIS` — en cours d’examen
- `VERIFICATION_REQUIRED` — renvoyé au client pour pièces ou informations
- `CREDIT_REVIEW` — examen approfondi
- `COMMITTEE` — présenté au comité
- `APPROVED` — crédit accordé, en attente de décaissement
- `REJECTED` — refusé (état final)
- `DISBURSED` — fonds mis à disposition (état final)
MD,
    enum: ['DRAFT', 'SUBMITTED', 'ANALYSIS', 'VERIFICATION_REQUIRED', 'CREDIT_REVIEW', 'COMMITTEE', 'APPROVED', 'REJECTED', 'DISBURSED'],
    example: 'DRAFT'
)]
#[OA\Schema(
    schema: 'RepaymentCapacityStatus',
    type: 'string',
    description: <<<'MD'
Capacité de remboursement estimée à la création / mise à jour du dossier.

- `SUFFICIENT` — le reste à vivre couvre la mensualité estimée
- `INSUFFICIENT` — la mensualité dépasse le reste à vivre
MD,
    enum: ['SUFFICIENT', 'INSUFFICIENT'],
    example: 'SUFFICIENT'
)]
#[OA\Schema(
    schema: 'CreditProductType',
    type: 'string',
    description: <<<'MD'
Produit de crédit. Filtré selon `client_type` du compte.

**Personne physique :** `MORTGAGE`, `CONSUMER_ASSIGNED`, `CONSUMER_PERSONAL`, `REVOLVING`, `STUDENT`, `PROFESSIONAL_WORKING_CAPITAL`

**Personne morale :** `INVESTMENT`, `OVERDRAFT`, `CASH_FACILITY`, `CAMPAIGN`, `DISCOUNT`, `FACTORING`, `LEASING`
MD,
    enum: [
        'MORTGAGE', 'CONSUMER_ASSIGNED', 'CONSUMER_PERSONAL', 'REVOLVING', 'STUDENT', 'PROFESSIONAL_WORKING_CAPITAL',
        'INVESTMENT', 'OVERDRAFT', 'CASH_FACILITY', 'CAMPAIGN', 'DISCOUNT', 'FACTORING', 'LEASING',
    ],
    example: 'CONSUMER_PERSONAL'
)]
#[OA\Schema(
    schema: 'ClientType',
    type: 'string',
    description: <<<'MD'
Nature du compte client à l’inscription.

- `PHYSICAL_PERSON` — personne physique (particulier, commerçant individuel)
- `LEGAL_ENTITY` — personne morale (entreprise demandant un crédit)
MD,
    enum: ['PHYSICAL_PERSON', 'LEGAL_ENTITY'],
    example: 'PHYSICAL_PERSON'
)]
#[OA\Schema(
    schema: 'LegalForm',
    type: 'string',
    description: <<<'MD'
Forme juridique de l’entreprise (personne morale uniquement).

- `SARL` — société à responsabilité limitée
- `SA` — société anonyme
- `SAS` — société par actions simplifiée
- `SAU` — société anonyme unipersonnelle
- `GIE` — groupement d’intérêt économique
- `SCOOP` — société cooperative
- `SCI` — société civile immobilière
- `EI` — entreprise individuelle
- `OTHER` — autre
MD,
    enum: ['SARL', 'SA', 'SAS', 'SAU', 'GIE', 'SCOOP', 'SCI', 'EI', 'OTHER'],
    example: 'SARL'
)]
#[OA\Schema(
    schema: 'KycStatus',
    type: 'string',
    description: <<<'MD'
Contrôle d’identité (profil client) ou décision de vérification d’une pièce KYC.

- `PENDING` — en attente d’examen
- `VERIFIED` — acceptée
- `REJECTED` — refusée
MD,
    enum: ['PENDING', 'VERIFIED', 'REJECTED'],
    example: 'PENDING'
)]
#[OA\Schema(
    schema: 'GuaranteeVerificationStatus',
    type: 'string',
    description: <<<'MD'
Contrôle d’une garantie déclarée.

- `PENDING` — déclarée, pas encore examinée
- `VERIFIED` — acceptée par l’équipe
- `REJECTED` — refusée
MD,
    enum: ['PENDING', 'VERIFIED', 'REJECTED'],
    example: 'PENDING'
)]
#[OA\Schema(
    schema: 'LoanStatus',
    type: 'string',
    description: <<<'MD'
État du prêt après décision.

- `APPROVED` — accordé, pas encore décaissé
- `ACTIVE` — décaissé, en cours de remboursement
- `CLOSED` — soldé
- `DEFAULTED` — en défaut
MD,
    enum: ['APPROVED', 'ACTIVE', 'CLOSED', 'DEFAULTED'],
    example: 'APPROVED'
)]
#[OA\Schema(
    schema: 'LoanRepaymentStatus',
    type: 'string',
    description: <<<'MD'
État d’une échéance.

- `PENDING` — à payer
- `PAID` — réglée
- `LATE` — en retard
MD,
    enum: ['PENDING', 'PAID', 'LATE'],
    example: 'PENDING'
)]
#[OA\Schema(
    schema: 'ScoringRecommendation',
    type: 'string',
    description: <<<'MD'
Avis du moteur ou de l’analyste. Ce n’est pas la décision d’octroi.

- `FAVORABLE` — avis positif
- `RESERVED` — avis mitigé
- `UNFAVORABLE` — avis négatif
MD,
    enum: ['FAVORABLE', 'RESERVED', 'UNFAVORABLE'],
    example: 'FAVORABLE'
)]
#[OA\Schema(
    schema: 'AnalystNextStep',
    type: 'string',
    description: <<<'MD'
Suite après la revue analyste.

- `COMMITTEE` — envoyer le dossier au comité
- `VERIFICATION_REQUIRED` — renvoyer le dossier au client
MD,
    enum: ['COMMITTEE', 'VERIFICATION_REQUIRED'],
    example: 'COMMITTEE'
)]
#[OA\Schema(
    schema: 'ValidationDecision',
    type: 'string',
    description: <<<'MD'
Décision humaine sur une pièce ou une information du dossier.

- `VALIDATED` — conforme
- `TO_COMPLETE` — à compléter (peut renvoyer le dossier au client)
- `REJECTED` — non conforme
MD,
    enum: ['VALIDATED', 'TO_COMPLETE', 'REJECTED'],
    example: 'VALIDATED'
)]
#[OA\Schema(
    schema: 'CommitteeDecision',
    type: 'string',
    description: <<<'MD'
Décision d’octroi du comité.

- `APPROVED` — accordé tel que demandé
- `AMENDED` — accordé avec montant et/ou durée modifiés
- `REJECTED` — refusé
MD,
    enum: ['APPROVED', 'REJECTED', 'AMENDED'],
    example: 'APPROVED'
)]
#[OA\Schema(
    schema: 'AnomalyStatus',
    type: 'string',
    description: <<<'MD'
Traitement d’un point à vérifier.

- `OPEN` — encore à traiter
- `RESOLVED` — traité
- `IGNORED` — écarté volontairement
MD,
    enum: ['OPEN', 'RESOLVED', 'IGNORED'],
    example: 'OPEN'
)]
#[OA\Schema(
    schema: 'AnomalySeverity',
    type: 'string',
    description: <<<'MD'
Gravité d’un point à vérifier.

- `LOW` — faible
- `MEDIUM` — moyenne
- `HIGH` — élevée
- `CRITICAL` — critique
MD,
    enum: ['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'],
    example: 'MEDIUM'
)]
#[OA\Schema(
    schema: 'ScoringMode',
    type: 'string',
    description: <<<'MD'
Mode du modèle de scoring.

- `STANDARD` — modèle unique : dossier avec compte en banque ou institution (IMF). Le Cold Start a été retiré.
MD,
    enum: ['STANDARD'],
    example: 'STANDARD'
)]
#[OA\Schema(
    schema: 'ScoringModelStatus',
    type: 'string',
    description: <<<'MD'
Cycle de vie d’un modèle de scoring. Un seul modèle `ACTIVE` à la fois.

- `DRAFT` — en préparation
- `ACTIVE` — utilisé pour les calculs
- `INACTIVE` — retiré
- `ARCHIVED` — archivé
MD,
    enum: ['DRAFT', 'ACTIVE', 'INACTIVE', 'ARCHIVED'],
    example: 'DRAFT'
)]
#[OA\Schema(
    schema: 'FactorType',
    type: 'string',
    description: <<<'MD'
Famille de règle / facteur du scoring.

- `income_consistency` — cohérence des revenus
- `expense` — charges
- `activity` — ancienneté de l’activité
- `activity_vitality` — vitalité du cycle (récence, rythme, adéquation)
- `document` — pièces du dossier
- `savings` — épargne
- `credit_history` — historique de crédit
- `guarantee` — garanties
- `repayment_capacity` — capacité de remboursement
- `residential_zone` — zone de résidence
MD,
    enum: ['income_consistency', 'expense', 'activity', 'activity_vitality', 'document', 'savings', 'credit_history', 'guarantee', 'repayment_capacity', 'residential_zone'],
    example: 'repayment_capacity'
)]
#[OA\Schema(
    schema: 'CreditDocumentType',
    type: 'string',
    description: <<<'MD'
Type de pièce jointe à une demande. L’API accepte une chaîne (80 car. max) ; ces valeurs sont celles reconnues par l’OCR.

- `JUSTIFICATIF_DOMICILE`, `FACTURE_ELECTRICITE`, `CONTRAT_BAIL` — domicile / charges
- `RELEVE_BANCAIRE` — relevé
- `PREUVE_REVENU`, `ATTESTATION_REVENU`, `BULLETIN_PAIE` — revenus
- `PIECE_IDENTITE`, `CNI`, `PASSEPORT` — identité jointe au dossier (distinct du KYC profil)
MD,
    enum: ['JUSTIFICATIF_DOMICILE', 'FACTURE_ELECTRICITE', 'CONTRAT_BAIL', 'RELEVE_BANCAIRE', 'PREUVE_REVENU', 'ATTESTATION_REVENU', 'BULLETIN_PAIE', 'PIECE_IDENTITE', 'CNI', 'PASSEPORT'],
    example: 'JUSTIFICATIF_DOMICILE'
)]
#[OA\Schema(
    schema: 'KycDocumentType',
    type: 'string',
    description: <<<'MD'
Type de pièce d’identité du profil (KYC). Chaîne libre (80 car. max) ; valeurs habituelles :

- `CNI` — carte nationale d’identité
- `PASSEPORT`
- `PIECE_IDENTITE`
MD,
    enum: ['CNI', 'PASSEPORT', 'PIECE_IDENTITE'],
    example: 'CNI'
)]
#[OA\Schema(
    schema: 'GuaranteeType',
    type: 'string',
    description: <<<'MD'
Nature de la garantie déclarée. Chaîne libre (80 car. max) ; valeurs habituelles :

- `MATERIEL` — bien matériel
- `BOUTIQUE` — fonds de commerce / boutique
- `FONCIER` — terrain ou immeuble
- `EPARGNE` — épargne nantie
- `CAUTION` — caution personne
MD,
    enum: ['MATERIEL', 'BOUTIQUE', 'FONCIER', 'EPARGNE', 'CAUTION'],
    example: 'MATERIEL'
)]
#[OA\Schema(
    schema: 'FieldVisitType',
    type: 'string',
    description: <<<'MD'
Objet de la visite terrain du chargé de crédit.

- `ACTIVITY_SITE` — lieu d’activité / boutique / atelier
- `RESIDENCE` — domicile du client
- `GUARANTEE_ASSET` — bien proposé en garantie
- `OTHER` — autre vérification sur place
MD,
    enum: ['ACTIVITY_SITE', 'RESIDENCE', 'GUARANTEE_ASSET', 'OTHER'],
    example: 'ACTIVITY_SITE'
)]
#[OA\Schema(
    schema: 'FieldVisitStatus',
    type: 'string',
    description: <<<'MD'
Cycle de vie d’une visite terrain.

- `SCHEDULED` — rendez-vous planifié
- `IN_PROGRESS` — chargé sur place
- `COMPLETED` — rapport renseigné
- `CANCELLED` — annulée
- `NO_SHOW` — client absent
MD,
    enum: ['SCHEDULED', 'IN_PROGRESS', 'COMPLETED', 'CANCELLED', 'NO_SHOW'],
    example: 'SCHEDULED'
)]
#[OA\Schema(
    schema: 'FieldVisitOutcome',
    type: 'string',
    description: <<<'MD'
Conclusion du chargé après la visite (aide à la décision, pas octroi).

- `FAVORABLE` — constats cohérents
- `RESERVED` — réserves / points à clarifier
- `UNFAVORABLE` — écarts importants ou risque constaté
MD,
    enum: ['FAVORABLE', 'RESERVED', 'UNFAVORABLE'],
    example: 'FAVORABLE'
)]
#[OA\Schema(
    schema: 'NotificationType',
    type: 'string',
    description: <<<'MD'
Type de notification envoyée à l’utilisateur.

- `STATUS_UPDATE` — changement d’état du dossier
- `COMPLEMENTS_REQUESTED` — dossier renvoyé pour pièces
- `LOAN_DISBURSED` — fonds décaissés
- `PASSWORD_RESET` — mot de passe réinitialisé par un admin
- `FIELD_VISIT` — rendez-vous ou rapport de visite terrain
MD,
    enum: ['STATUS_UPDATE', 'COMPLEMENTS_REQUESTED', 'LOAN_DISBURSED', 'PASSWORD_RESET', 'FIELD_VISIT'],
    example: 'STATUS_UPDATE'
)]
class EnumSchemas {}
