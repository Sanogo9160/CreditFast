<?php

namespace Database\Factories;

use App\Models\Caisse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Caisse>
 */
class CaisseFactory extends Factory
{
    protected $model = Caisse::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('???')),
            'name' => 'Caisse de '.fake()->city(),
            'city' => fake()->city(),
            'is_active' => true,
        ];
    }
}
