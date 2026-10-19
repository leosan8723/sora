<?php

namespace Tests\Feature\Domain;

use App\Enums\UserRole;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantProfileRelationshipTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_belongs_to_a_consultant_user(): void
    {
        $profile = ConsultantProfile::factory()->create();

        $this->assertInstanceOf(User::class, $profile->user);
        $this->assertSame(UserRole::Consultant, $profile->user->role);
        $this->assertTrue($profile->user->isConsultant());
    }

    public function test_profile_is_reachable_from_its_user(): void
    {
        $user = User::factory()->role(UserRole::Consultant)->create();
        $profile = ConsultantProfile::factory()->create(['user_id' => $user->id]);

        $this->assertTrue($user->consultantProfile->is($profile));
    }

    public function test_specialties_are_a_synced_many_to_many_relationship(): void
    {
        $profile = ConsultantProfile::factory()->create();
        $specialties = Specialty::factory()->count(3)->create();

        $profile->specialties()->sync($specialties->pluck('id')->all());

        $this->assertCount(3, $profile->fresh()->specialties);
        $this->assertTrue($specialties->first()->consultantProfiles->contains($profile));
    }

    public function test_consultation_types_and_availability_rules_are_owned(): void
    {
        $profile = ConsultantProfile::factory()->create();

        ConsultationType::factory()->count(2)->create(['consultant_profile_id' => $profile->id]);
        $profile->availabilityRules()->createMany([
            ['weekday' => 0, 'start_time' => '09:00', 'end_time' => '13:00'],
            ['weekday' => 2, 'start_time' => '14:00', 'end_time' => '18:00'],
        ]);

        $this->assertCount(2, $profile->consultationTypes);
        $this->assertCount(2, $profile->availabilityRules);
    }

    public function test_discoverable_scope_requires_active_and_verified(): void
    {
        $discoverable = ConsultantProfile::factory()->create();
        ConsultantProfile::factory()->unverified()->create();
        ConsultantProfile::factory()->inactive()->create();

        $ids = ConsultantProfile::query()->discoverable()->pluck('id');

        $this->assertSame([$discoverable->id], $ids->all());
    }
}
