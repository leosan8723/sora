<?php

namespace Tests\Feature\Booking;

use App\Enums\UserRole;
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

class ConsultationModeTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private const TZ = 'Asia/Tehran';

    private CarbonImmutable $date;

    private ConsultantProfile $profile;

    private User $consultant;

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

        $this->travelTo($this->date->setTime(6, 0));
    }

    private function client(): User
    {
        return User::factory()->role(UserRole::Client)->create(['is_active' => true]);
    }

    private function createType(array $attributes = []): ConsultationType
    {
        return ConsultationType::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'duration_minutes' => 30,
            'price_toman' => 500000,
            'is_active' => true,
            ...$attributes,
        ]);
    }

    private function firstSlot(ConsultationType $type): CarbonImmutable
    {
        return app(SlotGenerator::class)
            ->candidateStarts($this->profile, $type, $this->date)
            ->first();
    }

    public function test_booking_snapshots_the_mode_of_the_consultation_type(): void
    {
        $type = $this->createType(['mode' => 'online']);
        $slot = $this->firstSlot($type);

        Sanctum::actingAs($this->client());
        $response = $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.mode', 'online');

        $this->assertDatabaseHas('appointments', [
            'consultation_type_id' => $type->id,
            'mode' => 'online',
        ]);

        // Changing the offering afterwards must not alter the booked snapshot.
        $type->update(['mode' => 'in_person']);

        $this->assertDatabaseHas('appointments', ['consultation_type_id' => $type->id, 'mode' => 'online']);
    }

    public function test_types_default_to_the_in_person_mode(): void
    {
        $type = $this->createType();

        $this->assertSame('in_person', $type->fresh()->mode->value);
    }

    public function test_consultant_can_set_the_mode_on_a_type(): void
    {
        Sanctum::actingAs($this->consultant);

        $this->postJson('/api/v1/consultant/consultation-types', [
            'title' => 'Ù…Ø´Ø§ÙˆØ±Ù‡ Ø¢Ù†Ù„Ø§ÛŒÙ†',
            'duration_minutes' => 45,
            'price_toman' => 700000,
            'mode' => 'online',
        ])->assertStatus(201)
            ->assertJsonPath('data.mode', 'online');

        $this->assertDatabaseHas('consultation_types', [
            'consultant_profile_id' => $this->profile->id,
            'mode' => 'online',
        ]);
    }

    public function test_mode_is_validated_against_supported_modes(): void
    {
        Sanctum::actingAs($this->consultant);

        $this->assertApiValidationError(
            $this->postJson('/api/v1/consultant/consultation-types', [
                'title' => 'Ù…Ø´Ø§ÙˆØ±Ù‡',
                'duration_minutes' => 45,
                'price_toman' => 700000,
                'mode' => 'telepathy',
            ]),
            'mode'
        );
    }

    public function test_mode_is_kept_when_rescheduling(): void
    {
        $type = $this->createType(['mode' => 'online']);
        $client = $this->client();
        $slot = $this->firstSlot($type);

        Sanctum::actingAs($client);
        $appointmentId = (int) $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ])->assertStatus(201)
            ->json('data.id');

        $nextSlot = $this->firstSlot($type)->addMinutes(30);

        Sanctum::actingAs($client);
        $this->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
            'starts_at' => $nextSlot->toIso8601ZuluString(),
        ])->assertStatus(200)
            ->assertJsonPath('data.mode', 'online');
    }

    public function test_public_detail_exposes_the_mode(): void
    {
        $type = $this->createType(['mode' => 'online']);

        $this->getJson("/api/v1/consultants/{$this->profile->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.consultation_types.0.mode', 'online')
            ->assertJsonPath('data.consultation_types.0.id', $type->id);
    }
}
