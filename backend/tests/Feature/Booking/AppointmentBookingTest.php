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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private const TZ = 'Asia/Tehran';

    private CarbonImmutable $date;

    private ConsultantProfile $profile;

    private ConsultationType $type;

    protected function setUp(): void
    {
        parent::setUp();

        // These flows exercise the primary Persian booking path; the test
        // client injects an English Accept-Language header by default.
        $this->withHeader('Accept-Language', 'fa');

        $this->date = CarbonImmutable::parse('2026-10-20', self::TZ); // Tuesday

        $consultant = User::factory()->role(UserRole::Consultant)->create(['is_active' => true]);
        $this->profile = ConsultantProfile::factory()->create([
            'user_id' => $consultant->id,
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

    private function firstSlot(): CarbonImmutable
    {
        return app(SlotGenerator::class)
            ->candidateStarts($this->profile, $this->type, $this->date)
            ->first();
    }

    public function test_client_can_book_a_valid_slot(): void
    {
        $client = $this->client();
        $slot = $this->firstSlot();

        Sanctum::actingAs($client);
        $response = $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
            'notes' => 'Ø§ÙˆÙ„ÛŒÙ† Ø¬Ù„Ø³Ù‡',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', AppointmentStatus::Pending->value)
            ->assertJsonPath('data.price_toman', 500000)
            ->assertJsonPath('data.duration_minutes', 30)
            ->assertJsonPath('data.starts_at', $slot->toIso8601ZuluString())
            ->assertJsonPath('data.starts_at_tehran', '2026-10-20 09:00')
            ->assertJsonPath('data.consultant.id', $this->profile->id)
            ->assertJsonPath('data.consultation_type.id', $this->type->id)
            ->assertJsonPath('data.can.cancel', true)
            ->assertJsonPath('data.can.reschedule', true);

        $this->assertDatabaseHas('appointments', [
            'client_id' => $client->id,
            'consultant_profile_id' => $this->profile->id,
            'status' => AppointmentStatus::Pending->value,
            'active_slot_key' => $this->profile->id.'|2026-10-20 05:30:00',
        ]);
    }

    public function test_price_is_snapshotted_from_the_consultation_type(): void
    {
        $client = $this->client();
        $slot = $this->firstSlot();

        Sanctum::actingAs($client);
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertStatus(201);

        $this->type->update(['price_toman' => 999000]);

        $this->assertDatabaseHas('appointments', ['price_toman' => 500000]);
    }

    public function test_booking_requires_a_bookable_slot(): void
    {
        $client = $this->client();

        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments', [
                'consultation_type_id' => $this->type->id,
                'starts_at' => $this->firstSlot()->addMinutes(7)->toIso8601ZuluString(),
            ]),
            'starts_at'
        );

        // Not on the availability grid at all (wrong day).
        Sanctum::actingAs($client);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments', [
                'consultation_type_id' => $this->type->id,
                'starts_at' => '2026-10-21T05:30:00Z',
            ]),
            'starts_at'
        );
    }

    public function test_a_past_slot_cannot_be_booked(): void
    {
        $this->travelTo($this->date->setTime(10, 0));

        Sanctum::actingAs($this->client());
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments', [
                'consultation_type_id' => $this->type->id,
                'starts_at' => $this->firstSlot()->toIso8601ZuluString(),
            ]),
            'starts_at'
        );
    }

    public function test_booking_is_validated(): void
    {
        Sanctum::actingAs($this->client());

        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments', [
                'consultation_type_id' => 999999,
                'starts_at' => 'not-a-date',
            ]),
            'consultation_type_id', 'starts_at'
        );
    }

    public function test_a_second_client_gets_a_conflict_for_the_same_slot(): void
    {
        $slot = $this->firstSlot();

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertStatus(201);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertStatus(409)
            ->assertJsonPath('errors.0.code', 'booking_conflict');
    }

    public function test_overlapping_slots_of_different_types_conflict(): void
    {
        // 09:00-10:00 (60 min) then 09:30-10:00 (30 min) overlap.
        $longType = ConsultationType::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'duration_minutes' => 60,
            'price_toman' => 900000,
            'is_active' => true,
        ]);

        $generator = app(SlotGenerator::class);
        $longStart = $generator->candidateStarts($this->profile, $longType, $this->date)->first();
        $overlapping = $generator->candidateStarts($this->profile, $this->type, $this->date)->get(1);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $longType->id,
            'starts_at' => $longStart->toIso8601ZuluString(),
        ])->assertStatus(201);

        Sanctum::actingAs($this->client());
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $overlapping->toIso8601ZuluString(),
        ])->assertStatus(409);
    }

    public function test_inactive_or_unverified_consultants_cannot_be_booked(): void
    {
        $slot = $this->firstSlot();

        $this->profile->update(['is_verified' => false]);

        Sanctum::actingAs($this->client());
        $this->assertApiValidationError(
            $this->postJson('/api/v1/appointments', [
                'consultation_type_id' => $this->type->id,
                'starts_at' => $slot->toIso8601ZuluString(),
            ]),
            'consultation_type_id'
        );
    }

    public function test_only_clients_and_authenticated_users_may_book(): void
    {
        $slot = $this->firstSlot();

        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertUnauthorized();

        $consultant = User::factory()->role(UserRole::Consultant)->create();
        Sanctum::actingAs($consultant);
        $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertForbidden();
    }

    public function test_the_database_rejects_two_active_appointments_on_one_slot(): void
    {
        $clientA = $this->client();
        $clientB = $this->client();
        $slot = $this->firstSlot();

        Appointment::factory()->create([
            'client_id' => $clientA->id,
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot,
            'ends_at' => $slot->addMinutes(30),
            'status' => AppointmentStatus::Pending,
            'price_toman' => 500000,
        ]);

        $this->expectException(QueryException::class);

        Appointment::factory()->create([
            'client_id' => $clientB->id,
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot,
            'ends_at' => $slot->addMinutes(30),
            'status' => AppointmentStatus::Confirmed,
            'price_toman' => 500000,
        ]);
    }
}
