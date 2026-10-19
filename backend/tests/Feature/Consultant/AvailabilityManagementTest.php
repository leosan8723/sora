<?php

namespace Tests\Feature\Consultant;

use App\Enums\UserRole;
use App\Models\AvailabilityRule;
use App\Models\ConsultantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class AvailabilityManagementTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private function consultant(): User
    {
        return User::factory()->role(UserRole::Consultant)->create();
    }

    public function test_consultant_can_create_list_update_and_delete_own_rule(): void
    {
        $user = $this->consultant();

        Sanctum::actingAs($user);
        $created = $this->postJson('/api/v1/consultant/availability', [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:30',
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.weekday', 1)
            ->assertJsonPath('data.start_time', '09:00')
            ->assertJsonPath('data.end_time', '12:30')
            ->assertJsonPath('data.is_active', true);

        $ruleId = $created->json('data.id');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/consultant/availability')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/availability/'.$ruleId, [
            'weekday' => 1,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'is_active' => false,
        ])->assertOk()
            ->assertJsonPath('data.start_time', '10:00')
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('availability_rules', [
            'id' => $ruleId,
            'start_time' => '10:00',
            'end_time' => '13:00',
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/consultant/availability/'.$ruleId)->assertNoContent();

        $this->assertDatabaseMissing('availability_rules', ['id' => $ruleId]);
    }

    public function test_rule_creation_is_validated(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->assertApiValidationError(
            $this->postJson('/api/v1/consultant/availability', [
                'weekday' => 9,
                'start_time' => '9:00',
                'end_time' => '08:00',
            ]),
            'weekday', 'start_time', 'end_time'
        );
    }

    public function test_overlapping_active_rule_on_the_same_weekday_is_rejected(): void
    {
        $user = $this->consultant();
        $profile = ConsultantProfile::factory()->create(['user_id' => $user->id]);
        AvailabilityRule::factory()->create([
            'consultant_profile_id' => $profile->id,
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $this->assertApiValidationError(
            $this->postJson('/api/v1/consultant/availability', [
                'weekday' => 1,
                'start_time' => '11:00',
                'end_time' => '13:00',
            ]),
            'start_time'
        );
    }

    public function test_adjacent_rules_do_not_overlap(): void
    {
        $user = $this->consultant();
        $profile = ConsultantProfile::factory()->create(['user_id' => $user->id]);
        AvailabilityRule::factory()->create([
            'consultant_profile_id' => $profile->id,
            'weekday' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_active' => true,
        ]);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/consultant/availability', [
            'weekday' => 2,
            'start_time' => '12:00',
            'end_time' => '14:00',
        ])->assertStatus(201);
    }

    public function test_a_rule_may_be_updated_without_colliding_with_itself(): void
    {
        $user = $this->consultant();
        $profile = ConsultantProfile::factory()->create(['user_id' => $user->id]);
        $rule = AvailabilityRule::factory()->create([
            'consultant_profile_id' => $profile->id,
            'weekday' => 3,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/availability/'.$rule->id, [
            'weekday' => 3,
            'start_time' => '09:30',
            'end_time' => '12:30',
        ])->assertOk()->assertJsonPath('data.start_time', '09:30');
    }

    public function test_consultant_cannot_modify_another_consultants_rule(): void
    {
        $otherProfile = ConsultantProfile::factory()->create();
        $rule = AvailabilityRule::factory()->create([
            'consultant_profile_id' => $otherProfile->id,
            'weekday' => 4,
            'start_time' => '09:00',
            'end_time' => '12:00',
        ]);

        Sanctum::actingAs($this->consultant());
        $this->putJson('/api/v1/consultant/availability/'.$rule->id, [
            'weekday' => 4,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ])->assertForbidden();

        Sanctum::actingAs($this->consultant());
        $this->deleteJson('/api/v1/consultant/availability/'.$rule->id)->assertForbidden();

        $this->assertDatabaseHas('availability_rules', [
            'id' => $rule->id,
            'start_time' => '09:00',
        ]);
    }

    public function test_managing_a_missing_rule_returns_not_found(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->putJson('/api/v1/consultant/availability/999999', [
            'weekday' => 1,
            'start_time' => '09:00',
            'end_time' => '10:00',
        ])->assertNotFound();
    }
}
