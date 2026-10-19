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
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DualCurrencyBookingTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Tehran';

    private ConsultantProfile $profile;

    private ConsultationType $type;

    private CarbonImmutable $date;

    protected function setUp(): void
    {
        parent::setUp();

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
            'price_usd_cents' => null,
            'is_active' => true,
        ]);

        $this->travelTo($this->date->setTime(6, 0));
    }

    private function book(string $acceptLanguage = 'fa', bool $verified = true): TestResponse
    {
        $client = User::factory()->role(UserRole::Client)->create([
            'is_active' => true,
            'email_verified_at' => $verified ? now() : null,
        ]);

        Sanctum::actingAs($client);

        $slot = app(SlotGenerator::class)
            ->candidateStarts($this->profile, $this->type, $this->date)
            ->first();

        return $this->postJson('/api/v1/appointments', [
            'consultation_type_id' => $this->type->id,
            'starts_at' => $slot->toIso8601ZuluString(),
        ], ['Accept-Language' => $acceptLanguage]);
    }

    public function test_persian_booking_without_usd_price_still_works_and_leaves_snapshot_null(): void
    {
        $response = $this->book('fa')->assertStatus(201);

        $response->assertJsonPath('data.price_toman', 500000);
        $response->assertJsonPath('data.price_usd_cents', null);
    }

    public function test_english_booking_without_usd_price_is_blocked_with_localized_error(): void
    {
        $before = $this->profile->appointments()->count();

        $response = $this->book('en')->assertStatus(422);

        $response->assertJsonPath('errors.0.field', 'consultation_type_id');
        $this->assertSame(__('booking.usd_price_required'), $response->json('errors.0.message'));

        $this->assertSame($before, $this->profile->appointments()->count());
    }

    public function test_english_booking_with_usd_price_snapshots_both_currencies(): void
    {
        $this->type->forceFill(['price_usd_cents' => 125000])->save();

        $response = $this->book('en')->assertStatus(201);

        $response->assertJsonPath('data.price_toman', 500000);
        $response->assertJsonPath('data.price_usd_cents', 125000);
    }

    public function test_public_types_and_detail_expose_usd_price_and_starting_price(): void
    {
        $this->type->forceFill(['price_usd_cents' => 125000])->save();

        $detail = $this->get('/api/v1/consultants/'.$this->profile->id, [
            'Accept-Language' => 'en',
        ]);

        $detail->assertOk();
        $this->assertSame(125000, $detail->json('data.starting_price_usd_cents'));
        $this->assertSame(125000, $detail->json('data.consultation_types.0.price_usd_cents'));
    }

    public function test_starting_price_usd_stays_null_when_no_type_has_a_usd_price(): void
    {
        $detail = $this->get('/api/v1/consultants/'.$this->profile->id, [
            'Accept-Language' => 'en',
        ]);

        $detail->assertOk();
        $this->assertNull($detail->json('data.starting_price_usd_cents'));
    }

    public function test_unverified_client_is_blocked_from_booking(): void
    {
        $response = $this->book('fa', verified: false)->assertStatus(422);

        $response->assertJsonPath('errors.0.field', 'email');
        $this->assertSame(__('booking.email_unverified'), $response->json('errors.0.message'));

        $this->assertSame(0, $this->profile->appointments()->count());
    }

    public function test_admin_can_set_and_clear_usd_price(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/consultation-types/'.$this->type->id, [
            'price_usd_cents' => 99000,
        ])->assertOk()
            ->assertJsonPath('data.price_usd_cents', 99000);

        Sanctum::actingAs($admin);

        $this->putJson('/api/v1/admin/consultation-types/'.$this->type->id, [
            'price_usd_cents' => null,
        ])->assertOk()
            ->assertJsonPath('data.price_usd_cents', null);
    }
}
