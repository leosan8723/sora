<?php

namespace Tests\Feature\Booking;

use App\Enums\AppointmentStatus;
use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use App\Services\Booking\SlotGenerator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class AppointmentStatusTransitionTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private const TZ = 'Asia/Tehran';

    private CarbonImmutable $date;

    private ConsultantProfile $profile;

    private User $consultant;

    private ConsultationType $type;

    protected function setUp(): void
    {
        parent::setUp();

        // These flows exercise the primary Persian booking path; the test
        // client injects an English Accept-Language header by default.
        $this->withHeader('Accept-Language', 'fa');

        $this->date = CarbonImmutable::parse('2026-10-20', self::TZ); // Tuesday

        $this->consultant = User::factory()->role(UserRole::Consultant)->create(['is_active' => true]);
        $this->profile = ConsultantProfile::factory()->create([
            'user_id' => $this->consultant->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        AvailabilityRule::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'weekday' => $this->date->dayOfWeek,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        $this->type = ConsultationType::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'duration_minutes' => 30,
            'price_toman' => 500000,
            'is_active' => true,
        ]);

        $this->travelTo($this->date->setTime(6, 0));
    }

    private function client(): User
    {
        return User::factory()->role(UserRole::Client)->create(['is_active' => true]);
    }

    private function slot(int $index = 0): CarbonImmutable
    {
        return app(SlotGenerator::class)
            ->candidateStarts($this->profile, $this->type, $this->date)
            ->get($index);
    }

    private function appointmentFor(
        User $client,
        ?CarbonImmutable $startsAt = null,
        AppointmentStatus $status = AppointmentStatus::Pending,
    ): Appointment {
        $startsAt ??= $this->slot();

        return Appointment::factory()->create([
            'client_id' => $client->id,
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(30),
            'status' => $status,
            'price_toman' => 500000,
        ]);
    }

    public function test_a_consultant_can_confirm_a_pending_appointment(): void
    {
        $appointment = $this->appointmentFor($this->client());

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => 'confirmed',
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value)
            ->assertJsonPath('data.can.confirm', false)
            ->assertJsonPath('data.can.complete', true);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Confirming keeps the slot held.
        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'active_slot_key' => $this->profile->id.'|'.$appointment->starts_at->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_a_consultant_can_complete_a_confirmed_appointment(): void
    {
        $appointment = $this->appointmentFor($this->client(), null, AppointmentStatus::Confirmed);

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Completed->value,
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Completed->value)
            ->assertJsonPath('data.can.complete', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Completed->value,
            'active_slot_key' => null,
        ]);
    }

    public function test_completing_frees_the_slot_for_others(): void
    {
        $appointment = $this->appointmentFor($this->client(), null, AppointmentStatus::Confirmed);

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Completed->value,
        ])->assertOk();

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $this->slot()->toIso8601ZuluString(),
        ])->assertStatus(201);
    }

    public function test_a_pending_appointment_cannot_be_completed(): void
    {
        $appointment = $this->appointmentFor($this->client());

        Sanctum::actingAs($this->consultant);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
                'status' => AppointmentStatus::Completed->value,
            ]),
            'status'
        );

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Pending->value,
        ]);
    }

    public function test_an_already_confirmed_appointment_cannot_be_confirmed_again(): void
    {
        $appointment = $this->appointmentFor($this->client(), null, AppointmentStatus::Confirmed);

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertStatus(422);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);
    }

    public function test_a_cancelled_appointment_cannot_be_transitioned(): void
    {
        $appointment = $this->appointmentFor($this->client(), null, AppointmentStatus::Cancelled);

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertForbidden();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Cancelled->value,
        ]);
    }

    public function test_clients_cannot_update_the_status(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertForbidden();

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Pending->value,
        ]);
    }

    public function test_other_consultants_cannot_update_the_status(): void
    {
        $appointment = $this->appointmentFor($this->client());

        $otherConsultant = User::factory()->role(UserRole::Consultant)->create();

        Sanctum::actingAs($otherConsultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertNotFound();
    }

    public function test_the_status_payload_is_validated(): void
    {
        $appointment = $this->appointmentFor($this->client());

        Sanctum::actingAs($this->consultant);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
                'status' => 'cancelled',
            ]),
            'status'
        );

        Sanctum::actingAs($this->consultant);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', []),
            'status'
        );
    }

    public function test_guests_cannot_update_the_status(): void
    {
        $appointment = $this->appointmentFor($this->client());

        $this->postJson('/api/v1/appointments/'.$appointment->id.'/status', [
            'status' => AppointmentStatus::Confirmed->value,
        ])->assertUnauthorized();
    }
}
