<?php

namespace Database\Factories;

use App\Models\CashDesk;
use App\Models\Guichet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashDesk>
 */
class CashDeskFactory extends Factory
{
    protected $model = CashDesk::class;

    public function definition(): array
    {
        return [
            'guichet_id' => Guichet::factory(),
            'code' => 'C'.fake()->unique()->numerify('##'),
            'label' => 'Case '.fake()->numerify('#'),
            'is_active' => true,
        ];
    }
}
