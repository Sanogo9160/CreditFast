<?php

namespace Database\Factories;

use App\Enums\BankAccountApplicationStatus;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Models\BankAccountApplication;
use App\Models\Caisse;
use App\Models\CashDesk;
use App\Models\Guichet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccountApplication>
 */
class BankAccountApplicationFactory extends Factory
{
    protected $model = BankAccountApplication::class;

    public function definition(): array
    {
        $caisse = Caisse::factory()->create();
        $guichet = Guichet::factory()->create(['caisse_id' => $caisse->id]);
        CashDesk::factory()->create(['guichet_id' => $guichet->id]);

        return [
            'caisse_id' => $caisse->id,
            'guichet_id' => $guichet->id,
            'status' => BankAccountApplicationStatus::Draft,
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'phone' => fake()->numerify('7#######'),
            'city' => 'Bamako',
            'address' => fake()->streetAddress(),
            'date_of_birth' => fake()->date(),
            'birth_place' => 'Bamako',
            'nationality' => 'Malienne',
            'gender' => Gender::Male,
            'marital_status' => MaritalStatus::Single,
            'id_document_type' => 'CNI',
            'id_document_number' => fake()->numerify('########'),
            'funds_origin' => 'Salaire',
            'account_main_usage' => 'Épargne',
        ];
    }
}
