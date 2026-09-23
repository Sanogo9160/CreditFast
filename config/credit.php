<?php

return [
    /*
    | Prototype institution rates. These are not official CIF/IMF tariffs
    | until the partner validates them.
    |
    | Single annual interest rate for every dossier (simulation, scoring
    | proposal, committee approval, loan). Clients cannot override it.
    */
    'interest' => [
        'annual_percent' => (float) env('CREDIT_ANNUAL_INTEREST_RATE', 15.0),
    ],

    /** @deprecated Use credit.interest.annual_percent */
    'annual_interest_rate_percent' => (float) env('CREDIT_ANNUAL_INTEREST_RATE', 15.0),

    'repayment_margin_percentage' => (float) env('CREDIT_REPAYMENT_MARGIN', 20.0),

    /*
    | Scoring: overall note never reaches 100 (prudence margin / score ceiling).
    | A credit request also requires at least one bank/IMF financial account.
    */
    'scoring' => [
        'overall_score_ceiling' => (float) env('CREDIT_SCORE_CEILING', 95.0),
    ],

    /*
    | Comptes internes de démarrage (pas de clients ni de dossiers fictifs).
    | Changer STAFF_BOOTSTRAP_PASSWORD après le premier déploiement.
    */
    'profile_photo' => [
        'max_kilobytes' => (int) env('PROFILE_PHOTO_MAX_KILOBYTES', 2048),
    ],

    'staff' => [
        'password' => env('STAFF_BOOTSTRAP_PASSWORD', 'ChangeMe-CreditFast-2026!'),
        'admin_email' => env('STAFF_ADMIN_EMAIL', 'admin@creditfast.ml'),
        'agent_email' => env('STAFF_AGENT_EMAIL', 'agent@creditfast.ml'),
        'analyst_email' => env('STAFF_ANALYST_EMAIL', 'analyste@creditfast.ml'),
        'committee_email' => env('STAFF_COMMITTEE_EMAIL', 'comite@creditfast.ml'),
    ],

    /*
    | Sur un hôte « production » servant aussi de sandbox docs (ex. Render),
    | autorise le seed idempotent des clients / staff d’atelier Swagger.
    */
    'seed_demo_users' => (bool) env('CREDITFAST_SEED_DEMO_USERS', false),
];
