<?php

namespace Tests\Feature\Admin;

use App\Models\ConsultantProfile;
use App\Models\ConsultantReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class AdminReviewModerationTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private User $admin;

    private ConsultantProfile $profile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
        $this->profile = ConsultantProfile::factory()->create(['is_verified' => true, 'is_active' => true]);
    }

    public function test_pending_reviews_are_listed_first(): void
    {
        $approved = ConsultantReview::factory()->forProfile($this->profile)->create(['status' => 'approved']);
        $pending = ConsultantReview::factory()->forProfile($this->profile)->pending()->create();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/reviews')
            ->assertOk()
            ->assertJsonPath('data.0.id', $pending->id)
            ->assertJsonPath('data.1.id', $approved->id);
    }

    public function test_reviews_can_be_filtered_by_status(): void
    {
        ConsultantReview::factory()->forProfile($this->profile)->create(['status' => 'approved']);
        ConsultantReview::factory()->forProfile($this->profile)->pending()->create();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/v1/admin/reviews?status=pending')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', 'pending');
    }

    public function test_approving_a_review_publishes_it(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->pending()->create();

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/reviews/{$review->id}", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('consultant_reviews', [
            'id' => $review->id,
            'status' => 'approved',
        ]);
        $this->assertNotNull($review->fresh()->moderated_at);

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_hiding_an_approved_review_removes_it_from_the_public_list(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->create(['status' => 'approved']);

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/v1/admin/reviews/{$review->id}", ['status' => 'hidden'])->assertOk();

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.reviews_count', 0);
    }

    public function test_status_must_be_a_known_value(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->pending()->create();

        Sanctum::actingAs($this->admin);
        $this->assertApiValidationError(
            $this->putJson("/api/v1/admin/reviews/{$review->id}", ['status' => 'published']),
            'status'
        );
    }

    public function test_admin_can_delete_a_review(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->create();

        Sanctum::actingAs($this->admin);
        $this->deleteJson("/api/v1/admin/reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('consultant_reviews', ['id' => $review->id]);
    }

    public function test_non_admins_cannot_moderate_reviews(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->pending()->create();
        $client = User::factory()->create();

        Sanctum::actingAs($client);
        $this->getJson('/api/v1/admin/reviews')->assertForbidden();

        Sanctum::actingAs($client);
        $this->putJson("/api/v1/admin/reviews/{$review->id}", ['status' => 'approved'])->assertForbidden();

        Sanctum::actingAs($client);
        $this->deleteJson("/api/v1/admin/reviews/{$review->id}")->assertForbidden();

        $this->assertDatabaseHas('consultant_reviews', ['id' => $review->id, 'status' => 'pending']);
    }
}
