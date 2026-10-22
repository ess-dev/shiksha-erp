<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database (local development demo data).
     */
    public function run(): void
    {
        $this->call([
            AccessCatalogSeeder::class,
            DemoSchoolsSeeder::class,
            DemoStaffSeeder::class,
            DemoStructureSeeder::class,
        ]);
    }
}
