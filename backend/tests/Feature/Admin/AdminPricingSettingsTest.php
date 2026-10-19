<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

/**
 * Pricing and supported-mode configuration lifecycle: persisting changes,
 * snapshot semantics for existing bookings, and the effect of the
 * supported_modes setting on consultant offerings.
 */
class AdminPricingSettingsTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private User $admin;

    private ConsultantProfile $profile;

    private ConsultationType $type;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $consultant = User::factory()->consultant()->create(['is_active' => true]);
        $this->profile = ConsultantProfile::factory()->create([
            'user_id' => $consultant->id,
            'is_active' => true,
            'is_verified' => true,
        ]);

        AvailabilityRule::factory()->create([
            'consultant_profile_id' => $this->profile->id,
            'weekday' => 2, // Tuesday
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

    public function test_admin_can_change_the_price_of_a_consultation_type(): void
    {
        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/consultation-types/{$this->type->id}", [
            'price_toman' => 750000,
        ])->assertStatus(200)
            ->assertJsonPath('data.price_toman', 750000)
            ->assertJsonPath('data.mode', 'in_person');

        $this->assertDatabaseHas('consultation_types', [
            'id' => $this->type->id,
            'price_toman' => 750000,
        ]);
    }

    public function test_price_validation_is_enforced_on_the_server(): void
    {
        Sanctum::actingAs($this->admin);
        $this->assertApiValidationError(
            $this->putJson("/api/v1/admin/consultation-types/{$this->type->id}", [
                'price_toman' => -5,
            ]),
            'price_toman'
        );

        $this->assertDatabaseHas('consultation_types', [
            'id' => $this->type->id,
            'price_toman' => 500000,
        ]);
    }

    public function test_existing_bookings_keep_their_price_snapshot_after_a_price_change(): void
    {
        // A historical booking made with the old price.
        $old = Appointment::factory()->confirmed()->create([
            'consultant_profile_id' => $this->profile->id,
            'consultation_type_id' => $this->type->id,
            'price_toman' => 500000,
        ]);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/consultation-types/{$this->type->id}", [
            'price_toman' => 980000,
        ])->assertStatus(200);

        // The historical booking is untouched...
        $this->assertDatabaseHas('appointments', [
            'id' => $old->id,
            'price_toman' => 500000,
        ]);

        // ...and the change is reflected in the slot/detail APIs.
        $this->getJson("/api/v1/consultants/{$this->profile->id}")
            ->assertStatus(200)
            ->assertJsonPath('data.consultation_types.0.price_toman', 980000)
            ->assertJsonPath('data.starting_price_toman', 980000);
    }

    public function test_non_admins_cannot_change_pricing(): void
    {
        $consultant = $this->profile->user;

        Sanctum::actingAs($consultant);
        $this->putJson("/api/v1/admin/consultation-types/{$this->type->id}", [
            'price_toman' => 10,
        ])->assertStatus(403);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson("/api/v1/admin/consultation-types/{$this->type->id}", [
            'price_toman' => 10,
        ])->assertStatus(403);

        $this->assertDatabaseHas('consultation_types', [
            'id' => $this->type->id,
            'price_toman' => 500000,
        ]);
    }

    public function test_admin_settings_persist_and_shape_the_consultant_offerings(): void
    {
        $consultant = $this->profile->user;

        Sanctum::actingAs($this->admin);
        $this->putJson('/api/v1/admin/settings', [
            'supported_modes' => ['online'],
            'contact_lines' => ['پشتیبانی فقط از طریق فرم تماس'],
        ])->assertStatus(200)
            ->assertJsonPath('supported_modes', ['online'])
            ->assertJsonPath('contact_lines', ['پشتیبانی فقط از طریق فرم تماس']);

        $this->actAs($consultant);

        // in_person is now disabled for new offerings...
        $this->assertApiValidationError(
            $this->postJson('/api/v1/consultant/consultation-types', [
                'title' => 'جلسه حضوری',
                'duration_minutes' => 45,
                'price_toman' => 600000,
                'mode' => 'in_person',
            ]),
            'mode'
        );

        // ...while online is accepted.
        $this->actAs($consultant);
        $this->postJson('/api/v1/consultant/consultation-types', [
            'title' => 'جلسه آنلاین',
            'duration_minutes' => 45,
            'price_toman' => 600000,
            'mode' => 'online',
        ])->assertStatus(201)
            ->assertJsonPath('data.mode', 'online');

        // Existing types are untouched by the policy change.
        $this->assertDatabaseHas('consultation_types', [
            'id' => $this->type->id,
            'mode' => 'in_person',
        ]);
    }

    public function test_settings_reject_an_empty_mode_list(): void
    {
        Sanctum::actingAs($this->admin);
        $this->assertApiValidationError(
            $this->putJson('/api/v1/admin/settings', [
                'supported_modes' => [],
            ]),
            'supported_modes'
        );

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/settings')
            ->assertStatus(200)
            ->assertJsonPath('supported_modes.0', 'online');
    }

    private function actAs(User $user): void
    {
        Sanctum::actingAs($user);
    }
}
