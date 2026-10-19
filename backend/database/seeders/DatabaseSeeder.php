<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with development-only, fictional data.
     */
    public function run(): void
    {
        // Production databases must only contain real accounts; demo data is
        // for development/staging and is hard-disabled there (fail safe).
        if (app()->isProduction()) {
            throw new \RuntimeException(
                'Demo data seeding is disabled in production. Use "php artisan admin:create".'
            );
        }

        $this->call([
            SpecialtySeeder::class,
            DemoDataSeeder::class,
        ]);
    }
}
