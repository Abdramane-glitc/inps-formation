<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Seeder par défaut : référentiel initial uniquement (sûr en production).
 * Les données de démonstration fictives sont chargées séparément :
 *   php artisan db:seed --class=DemoSeeder   (refusé en production)
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferentielInitialSeeder::class);
    }
}
