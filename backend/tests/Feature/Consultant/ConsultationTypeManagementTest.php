<?php

namespace Tests\Feature\Consultant;

use App\Enums\UserRole;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ConsultationTypeManagementTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private function consultant(): User
    {
        return User::factory()->role(UserRole::Consultant)->create();
    }

    public function test_consultant_can_create_list_update_and_delete_own_type(): void
    {
        $user = $this->consultant();

        Sanctum::actingAs($user);
        $created = $this->postJson('/api/v1/consultant/consultation-types', [
            'title' => 'جلسه آنلاین',
            'description' => 'جلسه ۴۵ دقیقه‌ای آنلاین',
            'duration_minutes' => 45,
            'price_toman' => 900000,
        ]);

        $created->assertStatus(201)
            ->assertJsonPath('data.title', 'جلسه آنلاین')
            ->assertJsonPath('data.duration_minutes', 45);

        $typeId = $created->json('data.id');

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/consultant/consultation-types')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        Sanctum::actingAs($user);
        $this->putJson('/api/v1/consultant/consultation-types/'.$typeId, [
            'price_toman' => 1200000,
            'is_active' => false,
        ])->assertOk()->assertJsonPath('data.price_toman', 1200000);

        $this->assertDatabaseHas('consultation_types', [
            'id' => $typeId,
            'price_toman' => 1200000,
            'is_active' => false,
        ]);

        Sanctum::actingAs($user);
        $this->deleteJson('/api/v1/consultant/consultation-types/'.$typeId)->assertNoContent();

        $this->assertDatabaseMissing('consultation_types', ['id' => $typeId]);
    }

    public function test_creation_is_validated(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->assertApiValidationError(
            $this->postJson('/api/v1/consultant/consultation-types', [
                'title' => '',
                'duration_minutes' => 1,
                'price_toman' => -10,
            ]),
            'title', 'duration_minutes', 'price_toman'
        );
    }

    public function test_consultant_cannot_modify_another_consultants_type(): void
    {
        $otherProfile = ConsultantProfile::factory()->create();
        $type = ConsultationType::factory()->create(['consultant_profile_id' => $otherProfile->id]);

        Sanctum::actingAs($this->consultant());
        $this->putJson('/api/v1/consultant/consultation-types/'.$type->id, [
            'title' => 'تغییر غیرمجاز',
        ])->assertForbidden();

        Sanctum::actingAs($this->consultant());
        $this->deleteJson('/api/v1/consultant/consultation-types/'.$type->id)->assertForbidden();

        $this->assertDatabaseHas('consultation_types', [
            'id' => $type->id,
            'title' => $type->title,
        ]);
    }

    public function test_managing_a_missing_type_returns_not_found(): void
    {
        Sanctum::actingAs($this->consultant());

        $this->putJson('/api/v1/consultant/consultation-types/999999', ['title' => 'x'])
            ->assertNotFound();
    }
}
