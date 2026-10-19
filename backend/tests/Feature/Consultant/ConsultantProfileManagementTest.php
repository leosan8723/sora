<?php

namespace Tests\Feature\Consultant;

use App\Enums\UserRole;
use App\Models\ConsultantProfile;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ConsultantProfileManagementTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private function consultant(): User
    {
        return User::factory()->role(UserRole::Consultant)->create();
    }

    public function test_consultant_can_view_and_update_own_profile(): void
    {
        $user = $this->consultant();
        ConsultantProfile::factory()->create(['user_id' => $user->id]);

        Sanctum::actingAs($user);
        $show = $this->getJson('/api/v1/consultant/profile');
        $show->assertOk()->assertJsonPath('data.user_id', $user->id);

        Sanctum::actingAs($user);
        $update = $this->putJson('/api/v1/consultant/profile', [
            'headline' => 'روان‌شناس بالینی',
            'bio' => 'ده سال تجربه در درمان اضطراب.',
            'years_experience' => 10,
        ]);

        $update->assertOk()
            ->assertJsonPath('data.headline', 'روان‌شناس بالینی')
            ->assertJsonPath('data.years_experience', 10);

        $this->assertDatabaseHas('consultant_profiles', [
            'user_id' => $user->id,
            'headline' => 'روان‌شناس بالینی',
            'years_experience' => 10,
        ]);
    }

    public function test_profile_update_is_validated(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->assertApiValidationError(
            $this->putJson('/api/v1/consultant/profile', ['years_experience' => -5]),
            'years_experience'
        );
    }

    public function test_consultant_can_sync_specialties(): void
    {
        $user = $this->consultant();
        $first = Specialty::factory()->create();
        $second = Specialty::factory()->create();

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/specialties', [
            'specialties' => [$first->id, $second->id],
        ])->assertOk()->assertJsonCount(2, 'data.specialties');

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/specialties', [
            'specialties' => [$second->id],
        ])->assertOk()->assertJsonCount(1, 'data.specialties');

        $this->assertSame(
            [$second->id],
            $user->consultantProfile->fresh()->specialties->pluck('id')->all()
        );
    }

    public function test_unknown_specialty_is_rejected(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->assertApiValidationError(
            $this->putJson('/api/v1/consultant/specialties', ['specialties' => [999999]]),
            'specialties.0'
        );
    }

    public function test_clients_cannot_access_consultant_management(): void
    {
        $client = User::factory()->role(UserRole::Client)->create();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/consultant/profile')->assertForbidden();

        Sanctum::actingAs($client);
        $this->putJson('/api/v1/consultant/profile', ['headline' => 'x'])->assertForbidden();
    }

    public function test_profile_show_returns_not_found_until_first_write(): void
    {
        $user = $this->consultant();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/consultant/profile')->assertNotFound();

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/profile', ['headline' => 'تازه‌وارد'])
            ->assertOk()
            ->assertJsonPath('data.headline', 'تازه‌وارد');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/consultant/profile')
            ->assertOk()
            ->assertJsonPath('data.headline', 'تازه‌وارد');
    }

    public function test_guests_receive_unauthorized(): void
    {
        $this->getJson('/api/v1/consultant/profile')->assertUnauthorized();
    }

    public function test_consultant_only_sees_own_profile(): void
    {
        $owner = $this->consultant();
        ConsultantProfile::factory()->create(['user_id' => $owner->id, 'headline' => 'پروفایل من']);

        $stranger = $this->consultant();
        ConsultantProfile::factory()->create(['user_id' => $stranger->id, 'headline' => 'پروفایل دیگری']);

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/consultant/profile')
            ->assertOk()
            ->assertJsonPath('data.headline', 'پروفایل من');
    }
}
