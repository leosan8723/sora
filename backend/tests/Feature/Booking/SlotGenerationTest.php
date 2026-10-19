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
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class SlotGenerationTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private const TZ = 'Asia/Tehran';

    private CarbonImmutable $date;

    private ConsultantProfile $profile;

    private ConsultationType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->date = CarbonImmutable::parse('2026-10-20', self::TZ); // Tuesday

        $user = User::factory()->role(UserRole::Consultant)->create(['is_active' => true]);
        $this->profile = ConsultantProfile::factory()->create([
            'user_id' => $user->id,
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
    }

    private function generator(): SlotGenerator
    {
        return app(SlotGenerator::class);
    }

    public function test_candidates_align_to_duration_within_rule_bounds(): void
    {
        $starts = $this->generator()->candidateStarts($this->profile, $this->type, $this->date);

        $this->assertCount(6, $starts);
        $this->assertSame('2026-10-20T05:30:00Z', $starts->first()->toIso8601ZuluString());
        $this->assertSame('2026-10-20T08:00:00Z', $starts->last()->toIso8601ZuluString());
        $this->assertSame('09:00', $starts->first()->setTimezone(self::TZ)->format('H:i'));
    }

    public function test_tehran_availability_is_converted_to_utc(): void
    {
        $starts = $this->generator()->candidateStarts($this->profile, $this->type, $this->date);

        foreach ($starts as $start) {
            $this->assertSame('UTC', $start->timezoneName);
        }

        // Iran is UTC+03:30 year-round (no DST since 2022).
        $this->assertSame('05:30', $starts->first()->format('H:i'));
    }

    public function test_active_appointments_block_their_slot_and_adjacent_overlaps(): void
    {
        $starts = $this->generator()->candidateStarts($this->profile, $this->type, $this->date);
        $taken = $starts->get(2); // 10:00 Tehran

        Appointment::factory()->create([
            'client_id' => User::factory()->role(UserRole::Client),
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'starts_at' => $taken,
            'ends_at' => $taken->addMinutes(30),
            'status' => AppointmentStatus::Pending,
            'price_toman' => $this->type->price_toman,
        ]);

        $available = $this->generator()->availableStarts(
            $this->profile,
            $this->type,
            $this->date,
            $this->date->startOfDay(),
        );

        $this->assertCount(5, $available);
        $this->assertFalse($available->contains(fn ($slot) => $slot->equalTo($taken)));
    }

    public function test_terminal_appointments_do_not_block_slots(): void
    {
        $starts = $this->generator()->candidateStarts($this->profile, $this->type, $this->date);

        foreach ([AppointmentStatus::Cancelled, AppointmentStatus::Completed] as $index => $status) {
            $taken = $starts->get($index);

            Appointment::factory()->create([
                'client_id' => User::factory()->role(UserRole::Client),
                'consultant_profile_id' => $this->profile->id,
                'consultation_type_id' => $this->type->id,
                'starts_at' => $taken,
                'ends_at' => $taken->addMinutes(30),
                'status' => $status,
                'price_toman' => $this->type->price_toman,
            ]);
        }

        $available = $this->generator()->availableStarts(
            $this->profile,
            $this->type,
            $this->date,
            $this->date->startOfDay(),
        );

        $this->assertCount(6, $available);
    }

    public function test_already_started_slots_respect_the_lead_time(): void
    {
        $available = $this->generator()->availableStarts(
            $this->profile,
            $this->type,
            $this->date,
            $this->date->setTime(10, 20),
        );

        // 09:00, 09:30 and 10:00 are within the 5-minute lead at 10:20.
        $this->assertCount(3, $available);
        $this->assertSame('10:30', $available->first()->setTimezone(self::TZ)->format('H:i'));
    }

    public function test_is_within_availability_detects_off_grid_times(): void
    {
        $generator = $this->generator();
        $onGrid = $generator->candidateStarts($this->profile, $this->type, $this->date)->first();

        $this->assertTrue($generator->isWithinAvailability($this->profile, $this->type, $onGrid));
        $this->assertFalse($generator->isWithinAvailability($this->profile, $this->type, $onGrid->addMinutes(7)));
    }

    public function test_public_slots_endpoint_groups_by_day(): void
    {
        $this->travelTo($this->date->startOfDay());

        $response = $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots?'
            .http_build_query([
                'consultation_type' => $this->type->id,
                'from' => '2026-10-20',
                'to' => '2026-10-20',
            ]));

        $response->assertOk()
            ->assertJsonPath('meta.timezone', self::TZ)
            ->assertJsonPath('meta.duration_minutes', 30)
            ->assertJsonCount(1, 'data')
            ->assertJsonCount(6, 'data.0.slots')
            ->assertJsonPath('data.0.date', '2026-10-20')
            ->assertJsonPath('data.0.slots.0.starts_at', '2026-10-20T05:30:00Z')
            ->assertJsonPath('data.0.slots.0.label', '09:00');
    }

    public function test_public_slots_are_empty_for_a_day_without_availability(): void
    {
        $this->travelTo($this->date->startOfDay());

        // 2026-10-21 is a Wednesday with no rule.
        $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots?'
            .http_build_query([
                'consultation_type' => $this->type->id,
                'from' => '2026-10-21',
                'to' => '2026-10-21',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_public_slots_endpoint_is_validated(): void
    {
        $this->assertApiValidationError(
            $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots'),
            'consultation_type'
        );

        $this->assertApiValidationError(
            $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots?'
                .http_build_query([
                    'consultation_type' => $this->type->id,
                    'from' => '2026-01-01',
                    'to' => '2026-03-01',
                ])),
            'to'
        );
    }

    public function test_public_slots_require_a_public_consultant_and_owned_active_type(): void
    {
        $this->getJson('/api/v1/consultants/999999/slots?consultation_type='.$this->type->id)
            ->assertNotFound();

        $otherProfile = ConsultantProfile::factory()->create(['is_active' => true, 'is_verified' => true]);
        $otherType = ConsultationType::factory()->create([
            'consultant_profile_id' => $otherProfile->id,
            'is_active' => true,
        ]);

        $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots?consultation_type='.$otherType->id)
            ->assertNotFound();

        $this->type->update(['is_active' => false]);

        $this->getJson('/api/v1/consultants/'.$this->profile->id.'/slots?consultation_type='.$this->type->id)
            ->assertNotFound();
    }
}
