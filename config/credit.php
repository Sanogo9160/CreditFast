<?php

return [
    /*
    | Prototype institution rates. These are not official CIF/IMF tariffs
    | until the partner validates them.
    */
    'annual_interest_rate_percent' => (float) env('CREDIT_ANNUAL_INTEREST_RATE', 12.0),
    'repayment_margin_percentage' => (float) env('CREDIT_REPAYMENT_MARGIN', 20.0),

    /*
    | Comptes internes de démarrage (pas de clients ni de dossiers fictifs).
    | Changer STAFF_BOOTSTRAP_PASSWORD après le premier déploiement.
    */
    'staff' => [
        'password' => env('STAFF_BOOTSTRAP_PASSWORD', 'ChangeMe-CreditFast-2026!'),
        'admin_email' => env('STAFF_ADMIN_EMAIL', 'admin@creditfast.ml'),
        'agent_email' => env('STAFF_AGENT_EMAIL', 'agent@creditfast.ml'),
        'analyst_email' => env('STAFF_ANALYST_EMAIL', 'analyste@creditfast.ml'),
        'committee_email' => env('STAFF_COMMITTEE_EMAIL', 'comite@creditfast.ml'),
    ],
];
