<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (User::query()->where('email', 'admin@example.com')->exists()) {
            $this->command?->warn('Demo data already seeded; skipping.');

            return;
        }

        User::factory()->role(UserRole::Admin)->create([
            'name' => 'مدیر سامانه',
            'email' => 'admin@example.com',
            'email_verified_at' => now(),
        ]);

        $specialties = Specialty::query()->get();

        $consultants = collect(range(1, 6))->map(function (int $index) use ($specialties): ConsultantProfile {
            $user = User::factory()->role(UserRole::Consultant)->create([
                'email' => "consultant{$index}@example.com",
            ]);

            $profile = ConsultantProfile::factory()->create([
                'user_id' => $user->id,
            ]);

            $profile->specialties()->sync(
                $specialties->random(min(3, $specialties->count()))->pluck('id')->all()
            );

            ConsultationType::factory()->count(2)->create([
                'consultant_profile_id' => $profile->id,
            ]);

            foreach ([0, 1, 2, 4] as $weekday) {
                $profile->availabilityRules()->create([
                    'weekday' => $weekday,
                    'start_time' => '09:00',
                    'end_time' => '17:00',
                    'is_active' => true,
                ]);
            }

            return $profile;
        });

        $clients = User::factory()->role(UserRole::Client)->count(12)->create();

        $slot = 0;

        $consultants->each(function (ConsultantProfile $profile) use ($clients, &$slot): void {
            foreach ($profile->consultationTypes as $type) {
                foreach ([
                    AppointmentStatus::Confirmed,
                    AppointmentStatus::Pending,
                    AppointmentStatus::Completed,
                    AppointmentStatus::Cancelled,
                ] as $status) {
                    $slot++;
                    $this->createAppointment($profile, $type, $clients->random(), $status, $slot);
                }
            }
        });
    }

    private function createAppointment(
        ConsultantProfile $profile,
        ConsultationType $type,
        User $client,
        AppointmentStatus $status,
        int $slot,
    ): void {
        $startsAt = $status === AppointmentStatus::Completed
            ? now()->subDays(2 + $slot)->startOfHour()
            : now()->addDays(1 + intdiv($slot, 6))->setTime(9 + ($slot % 6), 0);

        $appointment = new Appointment([
            'client_id' => $client->id,
            'consultant_profile_id' => $profile->id,
            'consultation_type_id' => $type->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes($type->duration_minutes),
            'status' => $status,
            'price_toman' => $type->price_toman,
        ]);

        if ($status === AppointmentStatus::Cancelled) {
            $appointment->cancelled_at = now();
            $appointment->cancellation_reason = 'انصراف مشتری';
        }

        $appointment->save();
    }
}
