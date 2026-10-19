<?php

namespace Database\Factories;

use App\Models\Specialty;
use App\Support\SpecialtyCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Specialty>
 */
class SpecialtyFactory extends Factory
{
    public function definition(): array
    {
        $entry = fake()->unique()->randomElement(SpecialtyCatalog::all());

        return [
            'name' => $entry['name'],
            'name_en' => $entry['name_en'],
            'slug' => $entry['slug'],
            'description' => fake()->optional()->sentence(8),
            'sort_order' => fake()->numberBetween(0, 20),
        ];
    }
}
