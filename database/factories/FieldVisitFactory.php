<?php

namespace Database\Factories;

use App\Enums\FieldVisitStatus;
use App\Enums\FieldVisitType;
use App\Models\FieldVisit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FieldVisit>
 */
class FieldVisitFactory extends Factory
{
    protected $model = FieldVisit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'visit_type' => FieldVisitType::ActivitySite,
            'status' => FieldVisitStatus::Scheduled,
            'outcome' => null,
            'scheduled_at' => now()->addDay(),
            'started_at' => null,
            'completed_at' => null,
            'location_label' => 'Marché Médina, Bamako',
            'latitude' => 12.6392,
            'longitude' => -8.0029,
            'purpose' => 'Vérification de l’activité déclarée',
            'findings' => null,
            'recommendations' => null,
            'cancel_reason' => null,
        ];
    }
}
