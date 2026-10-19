<?php

namespace Database\Factories;

use App\Models\ConsultantProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultantProfile>
 */
class ConsultantProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role('consultant'),
            'headline' => fake()->randomElement([
                'مشاور بالینی و درمانگر شناختی-رفتاری',
                'روان‌شناس بالینی، ۱۲ سال تجربه درمانی',
                'مشاور خانواده و زوج‌درمانگر',
                'متخصص کودک و نوجوان',
            ]),
            'bio' => fake()->text(300),
            'years_experience' => fake()->numberBetween(1, 20),
            'is_verified' => true,
            'is_active' => true,
            'verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
