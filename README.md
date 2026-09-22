# CreditFast — Backend API

API Laravel pour une IMF (institution de microfinance) : inscription clients (personne physique / morale), demandes de crédit, scoring, parcours agent → analyste → comité, et administration.

Documentation interactive (Swagger) :  
[https://creditfast-api.onrender.com/api/documentation](https://creditfast-api.onrender.com/api/documentation)

---

## Stack

| Élément | Choix |
|---|---|
| Runtime | PHP 8.3+, Laravel 13 |
| Auth | Laravel Sanctum (Bearer token) |
| Docs API | L5-Swagger (`/api/documentation`) |
| Base locale | MySQL (Laragon) |
| Base distante (docs) | PostgreSQL sur Render |

---

## Démarrage local

```bash
cp .env.example .env
php artisan key:generate
# Configurer DB_* et STAFF_BOOTSTRAP_PASSWORD dans .env

composer install
php artisan migrate --seed
php artisan serve
# ou via Laragon : https://creditfast.test
```

Après seed (hors tests) : **un seul compte** admin.

| Champ | Valeur |
|---|---|
| Email | `STAFF_ADMIN_EMAIL` (défaut `admin@creditfast.ml`) |
| Mot de passe | `STAFF_BOOTSTRAP_PASSWORD` (défaut dans `.env.example`) |
| Login | `POST /api/auth/staff/login` |

Réinitialisation complète locale :

```bash
php artisan migrate:fresh --seed
```

---

## Rôles

Enum `App\Enums\RoleName` :

| Rôle API | Qui |
|---|---|
| `client` | Emprunteur (PP ou PM) |
| `credit_agent` | Chargé de crédit |
| `analyst` | Analyste risques |
| `committee_member` | Membre du comité |
| `admin` | Administration |

Les routes sont protégées par `auth:sanctum` + middleware `role:…`.

---

## Authentification

| Endpoint | Usage |
|---|---|
| `POST /api/auth/register` | Inscription client (`client_type` = `PHYSICAL_PERSON` \| `LEGAL_ENTITY`) |
| `POST /api/auth/client/login` | Login client (téléphone + mot de passe) |
| `POST /api/auth/staff/login` | Login staff (email + mot de passe) |
| `GET /api/auth/me` | Profil courant |
| `POST /api/auth/logout` | Révoque le token |

Header : `Authorization: Bearer {token}` + `Accept: application/json`.

---

## Types de client & produits de crédit

### Client

Enum `ClientType` :

| Type | Qui | Champs spécifiques |
|---|---|---|
| `PHYSICAL_PERSON` | Particulier / commerçant individuel | `first_name` / `last_name` = le client ; `occupation` possible au profil |
| `LEGAL_ENTITY` | Entreprise | `company_name` (raison sociale), `registration_number` (RCCM ou NIF, **unique**), `legal_form` (enum) obligatoires ; `trade_name` facultatif ; `first_name` / `last_name` = **représentant légal** |

Champs / sigles utiles pour une personne morale :

| Sigle / terme | Signification |
|---|---|
| **RCCM** | Registre du Commerce et du Crédit Mobilier (numéro d’immatriculation de l’entreprise au Mali / OHADA) |
| **NIF** | Numéro d’Identification Fiscale |
| **SARL / SA / …** | Forme juridique (`legal_form`) : société à responsabilité limitée, société anonyme, etc. |
| **IMF** | Institution de MicroFinance |
| **KYC** | *Know Your Customer* — vérification d’identité et des pièces du client |
| **PP / PM** | Personne physique / personne morale |

### Produits (`GET /api/credit-products`)

Les types de crédit sont un **enum PHP** `App\Enums\CreditProductType`.  
L’endpoint expose un catalogue dérivé de cet enum :

- **Client** → uniquement les produits compatibles avec son `client_type`
- **Staff** → catalogue complet

| Profil | Codes `credit_type` |
|---|---|
| Personne physique | `MORTGAGE`, `CONSUMER_ASSIGNED`, `CONSUMER_PERSONAL`, `REVOLVING`, `STUDENT`, `PROFESSIONAL_WORKING_CAPITAL` |
| Personne morale | `INVESTMENT`, `OVERDRAFT`, `CASH_FACILITY`, `CAMPAIGN`, `DISCOUNT`, `FACTORING`, `LEASING` |

À la création d’une demande (`POST /api/credit-requests`), `credit_type` est **obligatoire** et doit être compatible. Sinon → `422` (consulter `/api/credit-products`).

Chaque demande enregistre aussi un snapshot `borrower_type` (= `client_type` au moment de la création).

---

## Parcours métier (résumé)

```
Client inscrit
  → complète profil / KYC / activité / profil financier
  → GET /api/credit-products
  → POST /api/credit-requests (+ documents, garanties)
  → POST .../submit
       ↓
  Agent (/api/agent/…) : compléments, vérif KYC/garanties, **visites terrain**, envoi à l’analyse
       ↓
  Analyste (/api/analyst/…) : scoring, anomalies, review, validation humaine
       ↓
  Comité (/api/committee/…) : décision
       ↓
  Prêt (/api/loans/…) : décaissement & remboursements
```

**Visites terrain** (`/api/agent/field-visits`) : le chargé planifie un rendez-vous (`SCHEDULED`), démarre sur place (`IN_PROGRESS`), puis clôture avec un rapport (`COMPLETED` + `FAVORABLE`/`RESERVED`/`UNFAVORABLE`). Annulation ou absence client : `CANCELLED` / `NO_SHOW`. La visite aide la décision ; elle ne remplace pas le comité.

Scoring : moteur `CreditScoringEngine` (scorecard + `activity_vitality`). Modèle **STANDARD** uniquement — compte banque/IMF obligatoire. Note globale plafonnée (`CREDIT_SCORE_CEILING`, défaut 95) : marge de prudence, jamais 100 %.  
Admin : comptes, modèles de scoring, audit (`/api/admin/…`).

---

## Structure utile du code

```
app/
  Enums/           # ClientType, CreditProductType, RoleName, statuts…
  Http/Controllers/Api/
  Http/Requests/   # Validation
  Models/
  Services/        # CreditProductCatalog, scoring, passwords…
  OpenApi/         # Schémas Swagger
database/
  migrations/
  seeders/         # RoleSeeder, ScoringModelSeeder, AdminUserSeeder
routes/api.php
```

Seed production / local : rôles + règles de scoring + **admin seul**.  
Seed tests (`php artisan test`) : staff + comptes démo.

---

## Environnements

| | Locale | Documentation (Render) |
|---|---|---|
| URL typique | Laragon / `php artisan serve` | `https://creditfast-api.onrender.com` |
| Base | MySQL `creditfast` | Postgres Render |
| Swagger | `/api/documentation` (si généré) | [lien ci-dessus](https://creditfast-api.onrender.com/api/documentation) |

Les deux bases sont **indépendantes**. Vider la locale ne change pas Render, et inversement.

Alignement mot de passe admin : même valeur `STAFF_BOOTSTRAP_PASSWORD` dans `.env` local **et** dans les variables d’environnement Render.

Reset distant (one-shot) : variable `CREDITFAST_RESET_DATABASE=true` puis redeploy ; remettre à `false` ensuite (voir `docker/start.sh` / `render.yaml`).

---

## Tests & qualité

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

---

## Licence

Projet applicatif CreditFast — stack Laravel (MIT pour le framework).
