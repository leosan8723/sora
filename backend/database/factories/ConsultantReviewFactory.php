<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultantReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsultantReview>
 */
class ConsultantReviewFactory extends Factory
{
    public function definition(): array
    {
        $appointment = Appointment::factory()->completed()->create();

        return [
            'consultant_profile_id' => $appointment->consultant_profile_id,
            'client_id' => $appointment->client_id,
            'appointment_id' => $appointment->id,
            'rating' => $this->faker->numberBetween(1, 5),
            'body' => $this->faker->sentence(12),
            'status' => 'approved',
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (): array => ['status' => 'pending']);
    }

    public function forProfile(ConsultantProfile $profile): static
    {
        return $this->state(fn () => ['consultant_profile_id' => $profile->id]);
    }

    public function byUser(User $user): static
    {
        return $this->state(fn () => ['client_id' => $user->id]);
    }
}
