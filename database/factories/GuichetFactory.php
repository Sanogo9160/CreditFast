<?php

namespace Database\Factories;

use App\Models\Caisse;
use App\Models\Guichet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guichet>
 */
class GuichetFactory extends Factory
{
    protected $model = Guichet::class;

    public function definition(): array
    {
        return [
            'caisse_id' => Caisse::factory(),
            'code' => 'G'.fake()->unique()->numerify('##'),
            'name' => 'Guichet '.fake()->numerify('#'),
            'is_active' => true,
        ];
    }
}
