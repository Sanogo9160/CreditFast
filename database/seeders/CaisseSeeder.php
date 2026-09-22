<?php

namespace Database\Seeders;

use App\Models\Caisse;
use App\Models\CashDesk;
use App\Models\Guichet;
use Illuminate\Database\Seeder;

class CaisseSeeder extends Seeder
{
    public function run(): void
    {
        $bamako = Caisse::query()->updateOrCreate(
            ['code' => 'BKO'],
            ['name' => 'Caisse de Bamako', 'city' => 'Bamako', 'is_active' => true]
        );

        $g01 = Guichet::query()->updateOrCreate(
            ['caisse_id' => $bamako->id, 'code' => 'G01'],
            ['name' => 'Guichet ACI 2000', 'is_active' => true]
        );

        CashDesk::query()->updateOrCreate(
            ['guichet_id' => $g01->id, 'code' => 'C01'],
            ['label' => 'Case 1', 'is_active' => true]
        );

        CashDesk::query()->updateOrCreate(
            ['guichet_id' => $g01->id, 'code' => 'C02'],
            ['label' => 'Case 2', 'is_active' => true]
        );

        $sikasso = Caisse::query()->updateOrCreate(
            ['code' => 'SKO'],
            ['name' => 'Caisse de Sikasso', 'city' => 'Sikasso', 'is_active' => true]
        );

        $gSk = Guichet::query()->updateOrCreate(
            ['caisse_id' => $sikasso->id, 'code' => 'G01'],
            ['name' => 'Guichet Centre', 'is_active' => true]
        );

        CashDesk::query()->updateOrCreate(
            ['guichet_id' => $gSk->id, 'code' => 'C01'],
            ['label' => 'Case 1', 'is_active' => true]
        );
    }
}
