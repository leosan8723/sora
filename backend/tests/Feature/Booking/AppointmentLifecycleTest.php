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

class AppointmentLifecycleTest extends TestCase
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

    private function appointmentFor(User $client, ?CarbonImmutable $startsAt = null, AppointmentStatus $status = AppointmentStatus::Pending): Appointment
    {
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

    public function test_a_client_can_view_their_own_appointment(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/appointments/'.$appointment->id)
            ->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.consultant.id', $this->profile->id)
            ->assertJsonPath('data.consultation_type.id', $this->type->id);
    }

    public function test_a_consultant_sees_the_client_on_their_own_appointment(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        Sanctum::actingAs($this->consultant);
        $this->getJson('/api/v1/appointments/'.$appointment->id)
            ->assertOk()
            ->assertJsonPath('data.client.id', $client->id)
            ->assertJsonPath('data.can.reschedule', false)
            ->assertJsonPath('data.can.cancel', true);
    }

    public function test_appointments_are_not_visible_to_other_users(): void
    {
        $appointment = $this->appointmentFor($this->client());

        Sanctum::actingAs($this->client());
        $this->getJson('/api/v1/appointments/'.$appointment->id)->assertNotFound();

        $otherConsultant = User::factory()->role(UserRole::Consultant)->create();
        Sanctum::actingAs($otherConsultant);
        $this->getJson('/api/v1/appointments/'.$appointment->id)->assertNotFound();

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel', [])->assertNotFound();
    }

    public function test_a_client_can_cancel_and_the_slot_is_freed(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel', [
            'cancellation_reason' => 'ØªØºÛŒÛŒØ± Ø¨Ø±Ù†Ø§Ù…Ù‡',
        ])->assertOk()
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value)
            ->assertJsonPath('data.cancellation_reason', 'ØªØºÛŒÛŒØ± Ø¨Ø±Ù†Ø§Ù…Ù‡')
            ->assertJsonPath('data.can.cancel', false);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Cancelled->value,
            'cancelled_by_id' => $client->id,
            'active_slot_key' => null,
        ]);

        // The freed slot can be booked again by someone else.
        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $this->slot()->toIso8601ZuluString(),
        ])->assertStatus(201);
    }

    public function test_clients_cannot_cancel_inside_the_lead_time(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        $this->travelTo($this->date->setTime(8, 0)); // one hour before start

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel', []),
            'starts_at'
        );
    }

    public function test_consultants_can_cancel_close_to_the_start_time(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        $this->travelTo($this->date->setTime(8, 30));

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel', [
            'cancellation_reason' => 'ÙˆØ¶Ø¹ÛŒØª Ø§Ø¶Ø·Ø±Ø§Ø±ÛŒ',
        ])->assertOk()->assertJsonPath('data.status', AppointmentStatus::Cancelled->value);
    }

    public function test_terminal_appointments_cannot_be_cancelled(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client, $this->slot(), AppointmentStatus::Completed);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/cancel', [])->assertForbidden();
    }

    public function test_a_client_can_reschedule_to_a_free_slot(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client, $this->slot(0));
        $target = $this->slot(3);

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/reschedule', [
            'starts_at' => $target->toIso8601ZuluString(),
        ])->assertOk()
            ->assertJsonPath('data.starts_at', $target->toIso8601ZuluString())
            ->assertJsonPath('data.status', AppointmentStatus::Pending->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'active_slot_key' => $this->profile->id.'|'.$target->format('Y-m-d H:i:s'),
        ]);
    }

    public function test_rescheduling_onto_a_taken_slot_conflicts(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client, $this->slot(0));
        $this->appointmentFor($this->client(), $this->slot(3));

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/reschedule', [
            'starts_at' => $this->slot(3)->toIso8601ZuluString(),
        ])->assertStatus(409);
    }

    public function test_rescheduling_to_an_unavailable_time_is_rejected(): void
    {
        $client = $this->client();
        $appointment = $this->appointmentFor($client);

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments/'.$appointment->id.'/reschedule', [
                'starts_at' => '2026-10-21T05:30:00Z',
            ]),
            'starts_at'
        );
    }

    public function test_consultants_cannot_reschedule_appointments(): void
    {
        $appointment = $this->appointmentFor($this->client());

        Sanctum::actingAs($this->consultant);
        $this->postJson('/api/v1/appointments/'.$appointment->id.'/reschedule', [
            'starts_at' => $this->slot(3)->toIso8601ZuluString(),
        ])->assertForbidden();
    }
}
