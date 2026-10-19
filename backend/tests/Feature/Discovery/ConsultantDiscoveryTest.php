<?php

namespace Tests\Feature\Discovery;

use App\Enums\UserRole;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ConsultantDiscoveryTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private function consultant(array $userAttributes = [], array $profileAttributes = []): ConsultantProfile
    {
        $user = User::factory()->role(UserRole::Consultant)->create($userAttributes);

        return ConsultantProfile::factory()->create($profileAttributes + ['user_id' => $user->id]);
    }

    public function test_only_discoverable_consultants_are_listed(): void
    {
        $this->consultant(['name' => 'قابل مشاهده']);
        $this->consultant([], ['is_verified' => false]);
        $this->consultant([], ['is_active' => false]);
        $this->consultant(['is_active' => false]);

        $response = $this->getJson('/api/v1/consultants');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.name', 'قابل مشاهده');
    }

    public function test_consultants_can_be_filtered_by_specialty_slug(): void
    {
        $anxiety = Specialty::factory()->create(['slug' => 'anxiety']);
        $family = Specialty::factory()->create(['slug' => 'family']);

        $matching = $this->consultant(['name' => 'متخصص اضطراب']);

        $other = $this->consultant(['name' => 'متخصص خانواده']);

        $matching->specialties()->sync([$anxiety->id]);
        $other->specialties()->sync([$family->id]);

        $response = $this->getJson('/api/v1/consultants?specialty=anxiety');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'متخصص اضطراب');
    }

    public function test_consultants_can_be_searched_by_keyword(): void
    {
        $this->consultant(['name' => 'مهسا رضایی'], ['headline' => 'مشاور کودک و نوجوان']);
        $this->consultant(['name' => 'رضا کریمی'], ['headline' => 'زوج‌درمانگر']);

        $byName = $this->getJson('/api/v1/consultants?q=مهسا');
        $byName->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'مهسا رضایی');

        $byHeadline = $this->getJson('/api/v1/consultants?q=زوج');
        $byHeadline->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'رضا کریمی');
    }

    public function test_consultant_listing_is_paginated(): void
    {
        $this->consultant();
        $this->consultant();
        $this->consultant();

        $response = $this->getJson('/api/v1/consultants?per_page=2');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.current_page', 1);
    }

    public function test_consultant_detail_exposes_public_data_only(): void
    {
        $profile = $this->consultant(['name' => 'دکتر نمونه', 'phone' => '09120000000']);
        $profile->specialties()->sync([Specialty::factory()->create()->id]);
        ConsultationType::factory()->create(['consultant_profile_id' => $profile->id, 'title' => 'جلسه اول']);
        ConsultationType::factory()->inactive()->create(['consultant_profile_id' => $profile->id, 'title' => 'غیرفعال']);
        $profile->availabilityRules()->create(['weekday' => 1, 'start_time' => '09:00', 'end_time' => '12:00']);

        $response = $this->getJson('/api/v1/consultants/'.$profile->id);

        $response->assertOk()
            ->assertJsonPath('data.name', 'دکتر نمونه')
            ->assertJsonPath('data.consultation_types.0.title', 'جلسه اول')
            ->assertJsonCount(1, 'data.consultation_types')
            ->assertJsonPath('data.availability.0.weekday', 1);

        $this->assertArrayNotHasKey('email', $response->json('data'));
        $this->assertArrayNotHasKey('phone', $response->json('data'));
        $this->assertArrayNotHasKey('is_active', $response->json('data'));
        $this->assertArrayNotHasKey('user_id', $response->json('data'));
    }

    public function test_non_discoverable_consultant_detail_returns_not_found(): void
    {
        $unverified = $this->consultant([], ['is_verified' => false]);
        $inactive = $this->consultant([], ['is_active' => false]);

        $this->getJson('/api/v1/consultants/'.$unverified->id)->assertNotFound();
        $this->getJson('/api/v1/consultants/'.$inactive->id)->assertNotFound();
        $this->getJson('/api/v1/consultants/999999')->assertNotFound();
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->assertApiValidationError(
            $this->getJson('/api/v1/consultants?specialty=does-not-exist'),
            'specialty'
        );

        $this->assertApiValidationError(
            $this->getJson('/api/v1/consultants?sort=invalid'),
            'sort'
        );

        $this->assertApiValidationError(
            $this->getJson('/api/v1/consultants?per_page=500'),
            'per_page'
        );
    }

    public function test_specialties_are_listed_in_order(): void
    {
        // The API localizes specialty names per Accept-Language (Symfony test
        // requests default the header to en-us), so the Persian expectation
        // pins the fa locale explicitly; ordering is the actual subject here.
        Specialty::factory()->create(['name' => 'الف', 'sort_order' => 2]);
        Specialty::factory()->create(['name' => 'ب', 'sort_order' => 1]);

        $response = $this->getJson('/api/v1/specialties', ['Accept-Language' => 'fa']);

        $response->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.name', 'ب');
    }
}
