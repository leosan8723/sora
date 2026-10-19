<?php

namespace Database\Seeders;

use App\Models\Specialty;
use App\Support\SpecialtyCatalog;
use Illuminate\Database\Seeder;

class SpecialtySeeder extends Seeder
{
    public function run(): void
    {
        foreach (SpecialtyCatalog::all() as $index => $entry) {
            $specialty = Specialty::query()->firstOrCreate(
                ['slug' => $entry['slug']],
                [
                    'name' => $entry['name'],
                    'name_en' => $entry['name_en'],
                    'sort_order' => $index,
                ],
            );

            // Existing rows may predate the English column or carry stale
            // curated labels; the catalog is the source of truth for all
            // eight built-in slugs.
            $specialty->fill([
                'name' => $entry['name'],
                'name_en' => $entry['name_en'],
                'sort_order' => $index,
            ])->save();
        }
    }
}
