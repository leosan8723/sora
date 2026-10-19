<?php

namespace Database\Factories;

use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultationType>
 */
class ConsultationTypeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consultant_profile_id' => ConsultantProfile::factory(),
            'title' => fake()->randomElement([
                'مشاوره فردی حضوری',
                'جلسه آنلاین',
                'مشاوره پیش از ازدواج',
                'ارزیابی اولیه',
            ]),
            'description' => fake()->optional()->sentence(10),
            'duration_minutes' => 60,
            'price_toman' => fake()->randomElement([800000, 1200000, 1500000, 2000000]),
            'is_active' => true,
            'sort_order' => fake()->numberBetween(0, 5),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
