<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
    }

    public function test_it_creates_the_expected_shape_of_data(): void
    {
        $this->assertSame(8, Specialty::query()->count());
        $this->assertSame(6, ConsultantProfile::query()->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->count());
        $this->assertSame(6, User::query()->where('role', UserRole::Consultant)->count());
        $this->assertSame(12, User::query()->where('role', UserRole::Client)->count());
        $this->assertGreaterThan(0, Appointment::query()->count());
    }

    public function test_no_active_appointment_is_missing_its_slot_key(): void
    {
        $this->assertSame(0, Appointment::query()
            ->whereIn('status', ['pending', 'confirmed'])
            ->whereNull('active_slot_key')
            ->count());
    }

    public function test_no_terminal_appointment_keeps_a_slot_key(): void
    {
        $this->assertSame(0, Appointment::query()
            ->whereIn('status', ['completed', 'cancelled'])
            ->whereNotNull('active_slot_key')
            ->count());
    }

    public function test_every_appointment_type_belongs_to_its_consultant(): void
    {
        Appointment::query()->with(['consultantProfile', 'consultationType'])->each(function (Appointment $appointment) {
            $this->assertSame(
                $appointment->consultant_profile_id,
                $appointment->consultationType->consultant_profile_id,
            );
        });
    }

    public function test_seeded_emails_are_reserved_example_addresses(): void
    {
        User::query()->pluck('email')->each(function (string $email) {
            $this->assertMatchesRegularExpression('/@example\.(com|org|net)$/', $email);
        });
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();

        $this->assertSame(6, ConsultantProfile::query()->count());
        $this->assertSame(12, User::query()->where('role', UserRole::Client)->count());
    }
}
