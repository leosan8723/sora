<?php

namespace Database\Factories;

use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AvailabilityRule>
 */
class AvailabilityRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'consultant_profile_id' => ConsultantProfile::factory(),
            'weekday' => fake()->numberBetween(0, 6),
            'start_time' => fake()->randomElement(['09:00', '10:00', '14:00', '16:00']),
            'end_time' => fake()->randomElement(['12:00', '17:00', '18:00', '20:00']),
            'is_active' => true,
        ];
    }
}
