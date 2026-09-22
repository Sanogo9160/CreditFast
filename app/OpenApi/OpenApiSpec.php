<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

if (! defined('L5_SWAGGER_CONST_HOST')) {
    define('L5_SWAGGER_CONST_HOST', 'http://creditfast.test');
}

#[OA\Info(
    version: '1.0.0',
    title: 'CreditFast API',
    description: <<<'MD'
API de microcrédit CreditFast (Laravel Sanctum).

**Rôles (slug)** :
- `client` — Client / demandeur
- `credit_agent` — Chargé de crédit
- `analyst` — Analyste
- `committee_member` — Membre du comité
- `admin` — Administrateur

**Authentification (deux flux distincts)** :
- Clients : inscription `/api/auth/register` et connexion `/api/auth/client/login` (téléphone + mot de passe).
- Comptes internes : connexion `/api/auth/staff/login` (e-mail professionnel + mot de passe). Les comptes internes sont créés par un admin.

Dans Swagger UI, cliquer **Authorize** et coller le token (sans le préfixe `Bearer`).
MD
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'Hôte actuel (L5_SWAGGER_CONST_HOST)')]
#[OA\Server(url: 'http://creditfast.test', description: 'Laragon local (HTTP)')]
#[OA\SecurityScheme(
    securityScheme: 'sanctum',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'Sanctum',
    description: 'Token personnel Sanctum. Coller uniquement la valeur du champ `token` de la réponse login/register.'
)]
#[OA\Tag(name: 'Authentification client', description: 'Inscription et connexion client par numéro de téléphone unique. Public.')]
#[OA\Tag(name: 'Authentification interne', description: 'Connexion des comptes internes (admin, chargé, analyste, comité) par e-mail professionnel. Public.')]
#[OA\Tag(name: 'Session', description: 'Profil de session, déconnexion et changement de mot de passe. Authentifié.')]
#[OA\Tag(name: 'Photo de profil', description: 'CRUD de la photo de profil. Tous les comptes authentifiés.')]
#[OA\Tag(name: 'Profil client', description: 'Profil, activités, finances et pièces d’identité. Rôle : Client.')]
#[OA\Tag(name: 'Demandes de crédit', description: 'Dossiers, pièces jointes et garanties. Soumission. Le staff consulte.')]
#[OA\Tag(name: 'Documents', description: 'Téléchargement des fichiers (pièces de dossier et KYC).')]
#[OA\Tag(name: 'Scoring et analyse', description: 'Moteur d’aide à la décision. Calcul : Chargé, Analyste, Admin. Lecture : propriétaire ou staff.')]
#[OA\Tag(name: 'Chargé de crédit', description: 'File agent, fiches clients (lister/lire), compléments, vérifications, historique institutionnel. Rôles : Chargé, Admin.')]
#[OA\Tag(name: 'Visites terrain', description: 'Planification, démarrage et rapport d’inspection / rendez-vous sur le terrain. Rôles : Chargé, Admin.')]
#[OA\Tag(name: 'Analyste', description: 'Revue, validation humaine, anomalies. Rôles : Analyste, Admin.')]
#[OA\Tag(name: 'Comité', description: 'Décision d’octroi. Rôles : Membre du comité, Admin.')]
#[OA\Tag(name: 'Prêts', description: 'Consultation (Client + staff). Décaissement et remboursement : Chargé et Admin uniquement.')]
#[OA\Tag(name: 'Notifications', description: 'CRUD des notifications de l’utilisateur connecté (lister, lire, marquer lue, supprimer).')]
#[OA\Tag(name: 'Adhésion compte PP', description: 'Fiche d’adhésion personne physique. Client PHYSICAL_PERSON + file agent PP. Pas de classification risque.')]
#[OA\Tag(name: 'Adhésion compte PM', description: 'Fiche d’adhésion personne morale (dirigeants, UBO). Client LEGAL_ENTITY + file agent PM. Pas de classification risque.')]
#[OA\Tag(name: 'Administration', description: 'CRUD comptes (désactivation, réinitialisation du mot de passe), modèles de scoring, audit. Rôle : Admin.')]
#[OA\Tag(name: 'Administration caisses', description: 'CRUD du référentiel institutionnel : Caisse → Guichet → Case. Rôle : Admin. Ordre conseillé : créer caisse, puis guichet, puis au moins une case.')]
#[OA\Tag(name: 'Simulation', description: 'Comparaison de mensualités. Utilisateur authentifié.')]
#[OA\Parameter(
    parameter: 'CaisseId',
    name: 'caisse',
    description: 'Identifiant de la caisse',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'GuichetId',
    name: 'guichet',
    description: 'Identifiant du guichet',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'CashDeskId',
    name: 'cashDesk',
    description: 'Identifiant de la case (till)',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'FieldVisitId',
    name: 'fieldVisit',
    description: 'Identifiant de la visite terrain',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'CreditRequestId',
    name: 'creditRequest',
    description: 'Identifiant de la demande de crédit',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'LoanId',
    name: 'loan',
    description: 'Identifiant du prêt',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'RepaymentId',
    name: 'repayment',
    description: 'Identifiant de l’échéance',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'ClientId',
    name: 'client',
    description: 'Identifiant du client (fiche métier, pas l’utilisateur)',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'DocumentId',
    name: 'document',
    description: 'Identifiant du document de crédit',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'KycDocumentId',
    name: 'kycDocument',
    description: 'Identifiant de la pièce d’identité',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'GuaranteeId',
    name: 'guarantee',
    description: 'Identifiant de la garantie',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'AnomalyId',
    name: 'anomaly',
    description: 'Identifiant du point à vérifier',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'NotificationId',
    name: 'notification',
    description: 'Identifiant de la notification',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'ScoringModelId',
    name: 'scoringModel',
    description: 'Identifiant du modèle de scoring',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'FinancialAccountId',
    name: 'financialAccount',
    description: 'Identifiant du compte institutionnel',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'ActivityId',
    name: 'activity',
    description: 'Identifiant de l’activité économique',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Parameter(
    parameter: 'UserId',
    name: 'user',
    description: 'Identifiant de l’utilisateur',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'integer', example: 1)
)]
#[OA\Response(
    response: 'Unauthorized',
    description: 'Non authentifié — token manquant ou invalide.',
    content: new OA\JsonContent(ref: '#/components/schemas/Message')
)]
#[OA\Response(
    response: 'Forbidden',
    description: 'Accès refusé — le rôle de l’utilisateur ne permet pas cette action (HTTP 403).',
    content: new OA\JsonContent(ref: '#/components/schemas/Message')
)]
#[OA\Response(
    response: 'NotFound',
    description: 'Ressource introuvable.',
    content: new OA\JsonContent(ref: '#/components/schemas/Message')
)]
#[OA\Response(
    response: 'ValidationError',
    description: 'Données invalides ou incomplètes.',
    content: new OA\JsonContent(ref: '#/components/schemas/ValidationError')
)]
class OpenApiSpec {}
