<?php

namespace Tests\Feature\Domain;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentSlotTest extends TestCase
{
    use RefreshDatabase;

    private ConsultantProfile $profile;

    private ConsultationType $type;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profile = ConsultantProfile::factory()->create();
        $this->type = ConsultationType::factory()->create([
            'consultant_profile_id' => $this->profile->id,
        ]);
        $this->client = User::factory()->role('client')->create();
    }

    private function makeAppointment(array $overrides = []): Appointment
    {
        $startsAt = CarbonImmutable::parse('2026-11-01 10:00:00', 'UTC');

        return Appointment::query()->create(array_merge([
            'client_id' => $this->client->id,
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addHour(),
            'status' => AppointmentStatus::Pending,
            'price_toman' => 1000000,
        ], $overrides));
    }

    public function test_active_appointment_gets_a_derived_slot_key(): void
    {
        $appointment = $this->makeAppointment();

        $this->assertSame("{$this->profile->id}|2026-11-01 10:00:00", $appointment->active_slot_key);
    }

    public function test_terminal_appointments_have_no_slot_key(): void
    {
        $completed = $this->makeAppointment(['status' => AppointmentStatus::Completed]);
        $cancelled = $this->makeAppointment(['status' => AppointmentStatus::Cancelled]);

        $this->assertNull($completed->active_slot_key);
        $this->assertNull($cancelled->active_slot_key);
    }

    public function test_two_active_appointments_cannot_hold_the_same_slot(): void
    {
        $this->makeAppointment();

        $this->expectException(QueryException::class);

        $this->makeAppointment();
    }

    public function test_two_cancelled_appointments_may_share_a_slot(): void
    {
        $this->makeAppointment(['status' => AppointmentStatus::Cancelled]);
        $this->makeAppointment(['status' => AppointmentStatus::Cancelled]);

        $this->assertSame(2, Appointment::query()->count());
    }

    public function test_cancelling_an_appointment_frees_the_slot(): void
    {
        $appointment = $this->makeAppointment();
        $appointment->update(['status' => AppointmentStatus::Cancelled]);

        $this->assertNull($appointment->fresh()->active_slot_key);

        $replacement = $this->makeAppointment();

        $this->assertTrue($replacement->exists);
    }

    public function test_rescheduling_an_active_appointment_moves_the_slot_key(): void
    {
        $appointment = $this->makeAppointment();

        $appointment->update(['starts_at' => CarbonImmutable::parse('2026-11-01 11:00:00', 'UTC')]);

        $this->assertSame("{$this->profile->id}|2026-11-01 11:00:00", $appointment->fresh()->active_slot_key);
    }

    public function test_same_slot_is_allowed_for_different_consultants(): void
    {
        $this->makeAppointment();

        $otherProfile = ConsultantProfile::factory()->create();
        $otherType = ConsultationType::factory()->create(['consultant_profile_id' => $otherProfile->id]);

        $appointment = $this->makeAppointment([
            'consultant_profile_id' => $otherProfile->id,
            'consultation_type_id' => $otherType->id,
        ]);

        $this->assertSame("{$otherProfile->id}|2026-11-01 10:00:00", $appointment->active_slot_key);
    }
}
