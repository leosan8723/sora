<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()
            ->addDays(fake()->numberBetween(1, 21))
            ->setTime(fake()->numberBetween(9, 18), 0);

        return [
            'client_id' => User::factory()->role('client'),
            'consultant_profile_id' => ConsultantProfile::factory(),
            'consultation_type_id' => ConsultationType::factory(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHour(),
            'status' => AppointmentStatus::Pending,
            'price_toman' => fake()->randomElement([800000, 1200000, 1500000, 2000000]),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Appointment $appointment): void {
            $type = $appointment->consultationType;

            if ($type !== null && $type->consultant_profile_id !== $appointment->consultant_profile_id) {
                $type->update(['consultant_profile_id' => $appointment->consultant_profile_id]);
            }
        });
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Pending,
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Confirmed,
        ]);
    }

    public function completed(): static
    {
        return $this->state(function (array $attributes) {
            $endsAt = now()->subDays(fake()->numberBetween(1, 30));

            return [
                'starts_at' => $endsAt->copy()->subHour(),
                'ends_at' => $endsAt,
                'status' => AppointmentStatus::Completed,
            ];
        });
    }

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AppointmentStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => fake()->sentence(6),
        ]);
    }
}
