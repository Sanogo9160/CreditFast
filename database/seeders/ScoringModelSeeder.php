<?php

namespace Database\Seeders;

use App\Enums\FactorType;
use App\Enums\ScoringMode;
use App\Enums\ScoringModelStatus;
use App\Models\ScoringModel;
use Illuminate\Database\Seeder;

class ScoringModelSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Standard Model V1.0
        $standardModel = ScoringModel::updateOrCreate(
            ['version' => 'V1.0', 'scoring_mode' => ScoringMode::Standard],
            [
                'name' => 'Modèle Scoring Standard Microcrédit V1.0',
                'description' => 'Moteur d’évaluation du risque pour profils disposant d’historique bancaire ou crédit.',
                'status' => ScoringModelStatus::Active,
                'effective_from' => now(),
            ]
        );

        $standardRules = [
            [
                'rule_code' => 'R_CAP_01',
                'rule_name' => 'Capacité de Remboursement',
                'factor_type' => FactorType::RepaymentCapacity,
                'weight' => 25.0,
                'description' => 'Reste à vivre rapporté à la mensualité exigée',
                'priority' => 1,
            ],
            [
                'rule_code' => 'R_INC_01',
                'rule_name' => 'Cohérence des Revenus',
                'factor_type' => FactorType::IncomeConsistency,
                'weight' => 10.0,
                'description' => 'Écart entre déclarations et preuves vérifiées',
                'priority' => 2,
            ],
            [
                'rule_code' => 'R_VIT_01',
                'rule_name' => 'Vitalité du cycle d’activité',
                'factor_type' => FactorType::ActivityVitality,
                'weight' => 15.0,
                'description' => 'Récence des preuves, rythme sur 90 jours, adéquation de la durée au cycle du métier. Signal manquant = 50, jamais 0.',
                'priority' => 3,
                'rule_config' => [
                    'recency_weight' => 40,
                    'rhythm_weight' => 35,
                    'fit_weight' => 25,
                ],
            ],
            [
                'rule_code' => 'R_ACT_01',
                'rule_name' => 'Stabilité de l’Activité',
                'factor_type' => FactorType::Activity,
                'weight' => 5.0,
                'description' => 'Ancienneté et localisation de l’activité commerciale ou artisanale',
                'priority' => 4,
            ],
            [
                'rule_code' => 'R_EXP_01',
                'rule_name' => 'Niveau des Charges',
                'factor_type' => FactorType::Expense,
                'weight' => 10.0,
                'description' => 'Ratio des dépenses personnelles et charges récurrentes',
                'priority' => 5,
            ],
            [
                'rule_code' => 'R_DOC_01',
                'rule_name' => 'Conformité Documentaire',
                'factor_type' => FactorType::Document,
                'weight' => 10.0,
                'description' => 'Exactitude et lisibilité OCR des pièces transmises',
                'priority' => 6,
            ],
            [
                'rule_code' => 'R_SAV_01',
                'rule_name' => 'Régularité de l’Épargne',
                'factor_type' => FactorType::Savings,
                'weight' => 10.0,
                'description' => 'Comportement d’épargne et solde moyen disponible',
                'priority' => 7,
            ],
            [
                'rule_code' => 'R_HIS_01',
                'rule_name' => 'Historique de Remboursement',
                'factor_type' => FactorType::CreditHistory,
                'weight' => 10.0,
                'description' => 'Ponctualité des crédits antérieurs et absences d’impayés',
                'priority' => 8,
            ],
            [
                'rule_code' => 'R_GUA_01',
                'rule_name' => 'Garantie proposée',
                'factor_type' => FactorType::Guarantee,
                'weight' => 5.0,
                'description' => 'Valeur estimée de l’actif apporté en garantie',
                'priority' => 9,
            ],
            [
                'rule_code' => 'R_ZONE_01',
                'rule_name' => 'Zone d’habitation (optionnelle)',
                'factor_type' => FactorType::ResidentialZone,
                'weight' => 0.0,
                'description' => 'Facteur contextuel du guide : inactif tant que l’institution n’a pas validé un barème. Absence de zone ≠ pénalité d’historique.',
                'priority' => 10,
                'status' => 'INACTIVE',
            ],
        ];

        foreach ($standardRules as $rule) {
            $standardModel->rules()->updateOrCreate(['rule_code' => $rule['rule_code']], $rule);
        }

        // 2. Cold Start Model V1.0
        $coldStartModel = ScoringModel::updateOrCreate(
            ['version' => 'V1.0', 'scoring_mode' => ScoringMode::ColdStart],
            [
                'name' => 'Modèle Scoring Cold Start Inclusive V1.0',
                'description' => 'Moteur d’évaluation adapté aux nouveaux demandeurs sans historique bancaire.',
                'status' => ScoringModelStatus::Active,
                'effective_from' => now(),
            ]
        );

        $coldStartRules = [
            [
                'rule_code' => 'CS_CAP_01',
                'rule_name' => 'Capacité de Remboursement',
                'factor_type' => FactorType::RepaymentCapacity,
                'weight' => 30.0,
                'description' => 'Reste à vivre rapporté à la mensualité, en l’absence d’historique bancaire',
                'priority' => 1,
            ],
            [
                'rule_code' => 'CS_INC_01',
                'rule_name' => 'Cohérence des Revenus',
                'factor_type' => FactorType::IncomeConsistency,
                'weight' => 15.0,
                'description' => 'Écart entre le revenu indiqué et l’activité professionnelle',
                'priority' => 2,
            ],
            [
                'rule_code' => 'CS_VIT_01',
                'rule_name' => 'Vitalité du cycle d’activité',
                'factor_type' => FactorType::ActivityVitality,
                'weight' => 20.0,
                'description' => 'Preuve que l’activité tourne maintenant, et que le crédit colle au cycle. Signal manquant = 50, jamais 0.',
                'priority' => 3,
                'rule_config' => [
                    'recency_weight' => 40,
                    'rhythm_weight' => 35,
                    'fit_weight' => 25,
                ],
            ],
            [
                'rule_code' => 'CS_ACT_01',
                'rule_name' => 'Ancienneté et Terrain de l’Activité',
                'factor_type' => FactorType::Activity,
                'weight' => 10.0,
                'description' => 'Pérennité de l’activité commerciale locale',
                'priority' => 4,
            ],
            [
                'rule_code' => 'CS_EXP_01',
                'rule_name' => 'Taux de Charge Personnelle',
                'factor_type' => FactorType::Expense,
                'weight' => 10.0,
                'description' => 'Évaluation des dépendants et dépenses du foyer',
                'priority' => 5,
            ],
            [
                'rule_code' => 'CS_DOC_01',
                'rule_name' => 'Qualité des Justificatifs',
                'factor_type' => FactorType::Document,
                'weight' => 10.0,
                'description' => 'Cohérence et lisibilité des pièces (OCR ≠ authenticité ; un contrôle humain confirme ensuite les informations)',
                'priority' => 6,
            ],
            [
                'rule_code' => 'CS_GUA_01',
                'rule_name' => 'Couverture Garantie',
                'factor_type' => FactorType::Guarantee,
                'weight' => 5.0,
                'description' => 'Actifs et garanties matérielles',
                'priority' => 7,
            ],
            [
                'rule_code' => 'CS_ZONE_01',
                'rule_name' => 'Zone d’habitation (optionnelle)',
                'factor_type' => FactorType::ResidentialZone,
                'weight' => 0.0,
                'description' => 'Facteur contextuel du guide : inactif tant que l’institution n’a pas validé un barème.',
                'priority' => 8,
                'status' => 'INACTIVE',
            ],
        ];

        foreach ($coldStartRules as $rule) {
            $coldStartModel->rules()->updateOrCreate(['rule_code' => $rule['rule_code']], $rule);
        }
    }
}
