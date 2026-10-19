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

class AppointmentListingTest extends TestCase
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

    public function test_a_client_sees_only_their_own_upcoming_appointments(): void
    {
        $client = $this->client();
        $otherClient = $this->client();

        $mine = $this->appointmentFor($client, $this->slot(0));
        $theirs = $this->appointmentFor($otherClient, $this->slot(1));

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/appointments?scope=upcoming')
            ->assertOk();

        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $mine->id);

        $this->assertDatabaseHas('appointments', ['id' => $theirs->id]);
    }

    public function test_upcoming_orders_soonest_first_and_hides_past_appointments(): void
    {
        $client = $this->client();

        $later = $this->appointmentFor($client, $this->slot(4));
        $sooner = $this->appointmentFor($client, $this->slot(1));
        $cancelled = $this->appointmentFor($client, $this->slot(3), AppointmentStatus::Cancelled);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/appointments?scope=upcoming')
            ->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $sooner->id);
        $response->assertJsonPath('data.1.id', $later->id);

        $this->assertNull($cancelled->refresh()->active_slot_key);
    }

    public function test_past_scope_includes_terminal_and_already_started_appointments(): void
    {
        $client = $this->client();

        $completed = $this->appointmentFor($client, $this->slot(0), AppointmentStatus::Completed);
        $cancelled = $this->appointmentFor($client, $this->slot(5), AppointmentStatus::Cancelled);

        Sanctum::actingAs($client);
        $response = $this->getJson('/api/v1/appointments?scope=past')
            ->assertOk();

        $response->assertJsonCount(2, 'data');
        $response->assertJsonPath('data.0.id', $cancelled->id);
        $response->assertJsonPath('data.1.id', $completed->id);
    }

    public function test_a_started_appointment_moves_from_upcoming_to_past(): void
    {
        $client = $this->client();
        $this->appointmentFor($client, $this->slot(0));

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?scope=upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Twelve hours later the slot has already started.
        $this->travelTo($this->date->setTime(18, 0));

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?scope=upcoming')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?scope=past')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_the_status_filter_narrows_the_list(): void
    {
        $client = $this->client();

        $this->appointmentFor($client, $this->slot(0), AppointmentStatus::Confirmed);
        $this->appointmentFor($client, $this->slot(1), AppointmentStatus::Cancelled);

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?scope=all&status=confirmed')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?scope=all&status=cancelled')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_a_consultant_sees_their_own_bookings(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client, $this->slot(0));

        Sanctum::actingAs($this->consultant);
        $this->getJson('/api/v1/appointments?scope=upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $appointment->id)
            ->assertJsonPath('data.0.client.id', $client->id)
            ->assertJsonPath('data.0.can.cancel', true)
            ->assertJsonPath('data.0.can.reschedule', false);
    }

    public function test_a_consultant_without_a_profile_sees_an_empty_list(): void
    {
        $consultantWithoutProfile = User::factory()->role(UserRole::Consultant)->create();

        $this->appointmentFor($this->client(), $this->slot(0));

        Sanctum::actingAs($consultantWithoutProfile);
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_consultant_does_not_see_other_consultants_appointments(): void
    {
        $this->appointmentFor($this->client(), $this->slot(0));

        $otherConsultant = User::factory()->role(UserRole::Consultant)->create(['is_active' => true]);
        $otherProfile = ConsultantProfile::factory()->create([
            'user_id' => $otherConsultant->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        Appointment::factory()->create([
            'client_id' => $this->client()->id,
            'consultant_profile_id' => $otherProfile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $this->slot(3),
            'ends_at' => $this->slot(3)->addMinutes(30),
            'status' => AppointmentStatus::Pending,
            'price_toman' => 500000,
        ]);

        Sanctum::actingAs($otherConsultant);
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(1, 'data');

        Sanctum::actingAs($this->consultant);
        $this->getJson('/api/v1/appointments')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_list_is_paginated(): void
    {
        $client = $this->client();
        $this->appointmentFor($client, $this->slot(0));
        $this->appointmentFor($client, $this->slot(1));
        $this->appointmentFor($client, $this->slot(2));

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_guests_cannot_list_appointments(): void
    {
        $this->getJson('/api/v1/appointments')->assertUnauthorized();
    }

    public function test_the_index_is_validated(): void
    {
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->getJson('/api/v1/appointments?scope=whenever'),
            'scope'
        );

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->getJson('/api/v1/appointments?status=maybe'),
            'status'
        );

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->getJson('/api/v1/appointments?per_page=500'),
            'per_page'
        );
    }
}
