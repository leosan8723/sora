<?php

namespace Tests\Feature\Localization;

use App\Enums\UserRole;
use App\Models\ConsultantProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsultantLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private function makeConsultant(array $english = []): ConsultantProfile
    {
        $user = User::factory()->create();

        return ConsultantProfile::factory()->create($english + ['user_id' => $user->id]);
    }

    public function test_public_detail_uses_english_fields_for_english_requests(): void
    {
        $profile = $this->makeConsultant([
            'name_en' => 'Sara Kavosi',
            'headline_en' => 'Trauma-informed therapist',
            'bio_en' => 'Supports adults through anxiety and burnout recovery.',
        ]);

        $response = $this->get('/api/v1/consultants/'.$profile->id, [
            'Accept-Language' => 'en',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', 'Sara Kavosi');
        $response->assertJsonPath('data.headline', 'Trauma-informed therapist');
        $response->assertJsonPath('data.bio', 'Supports adults through anxiety and burnout recovery.');
    }

    public function test_public_detail_falls_back_to_persian_without_fabrication(): void
    {
        $profile = $this->makeConsultant();

        $response = $this->get('/api/v1/consultants/'.$profile->id, [
            'Accept-Language' => 'en',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', $profile->user->name);
        $response->assertJsonPath('data.headline', $profile->headline);
        $response->assertJsonPath('data.bio', $profile->bio);
    }

    public function test_public_detail_always_uses_persian_for_persian_requests(): void
    {
        $profile = $this->makeConsultant([
            'name_en' => 'Sara Kavosi',
            'headline_en' => 'English headline',
        ]);

        $response = $this->get('/api/v1/consultants/'.$profile->id, [
            'Accept-Language' => 'fa',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.name', $profile->user->name);
        $response->assertJsonPath('data.headline', $profile->headline);
        $this->assertNotSame('English headline', $response->json('data.headline'));
    }

    public function test_admin_can_edit_english_fields_and_sees_missing_translations(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $this->actingAs($admin, 'sanctum');

        $profile = $this->makeConsultant();

        $this->putJson("/api/v1/admin/consultants/{$profile->id}", [
            'name_en' => 'Sara Kavosi',
            'headline_en' => 'Trauma-informed therapist',
        ])->assertOk();

        $this->actingAs($admin, 'sanctum');
        $listed = $this->getJson('/api/v1/admin/consultants', [
            'Accept-Language' => 'en',
        ]);

        $missing = collect($listed->json('data'))
            ->first(fn ($row) => $row['id'] === $profile->id)['translation_missing_en'];

        $this->assertSame(['bio'], $missing);

        $this->actingAs($admin, 'sanctum');
        $this->putJson("/api/v1/admin/consultants/{$profile->id}", [
            'bio_en' => 'Supports adults through anxiety and burnout recovery.',
        ])->assertOk();

        $this->actingAs($admin, 'sanctum');
        $missingAfter = collect($this->getJson('/api/v1/admin/consultants')->json('data'))
            ->first(fn ($row) => $row['id'] === $profile->id)['translation_missing_en'];

        $this->assertSame([], $missingAfter);
    }

    public function test_consultant_can_edit_their_own_english_profile_fields(): void
    {
        $consultant = User::factory()->consultant()->create();
        $profile = ConsultantProfile::factory()->create(['user_id' => $consultant->id]);
        $this->actingAs($consultant, 'sanctum');

        $this->putJson('/api/v1/consultant/profile', [
            'name_en' => 'Consultant E2E',
        ])->assertOk();

        $this->assertSame('Consultant E2E', $profile->fresh()->name_en);

        // Role middleware keeps admin routes out of consultants' reach.
        $otherProfile = $this->makeConsultant();
        $this->actingAs($consultant, 'sanctum');

        $this->putJson("/api/v1/admin/consultants/{$otherProfile->id}", [
            'name_en' => 'Hijacked Name',
        ])->assertStatus(403);
    }
}
