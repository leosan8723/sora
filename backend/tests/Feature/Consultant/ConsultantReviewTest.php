<?php

namespace Tests\Feature\Consultant;

use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultantReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ConsultantReviewTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private ConsultantProfile $profile;

    private User $consultant;

    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->consultant = User::factory()->consultant()->create(['name' => 'مریم رضایی']);
        $this->profile = ConsultantProfile::factory()
            ->for($this->consultant)
            ->create(['is_verified' => true, 'is_active' => true]);

        $this->client = User::factory()->create(['name' => 'سارا محمدی']);
    }

    public function test_approved_reviews_are_public_with_aggregates(): void
    {
        $other = User::factory()->create(['name' => 'علی کاظمی']);

        ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)->create([
            'rating' => 5,
            'body' => 'جلسه بسیار مفیدی بود، ممنون.',
            'status' => 'approved',
        ]);
        ConsultantReview::factory()->forProfile($this->profile)->byUser($other)->create([
            'rating' => 3,
            'body' => 'خوب بود اما کوتاه.',
            'status' => 'approved',
        ]);
        ConsultantReview::factory()->forProfile($this->profile)->pending()->create();

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.avg_rating', 4)
            ->assertJsonPath('meta.reviews_count', 2)
            ->assertJsonPath('data.0.body', 'خوب بود اما کوتاه.');
    }

    public function test_authors_can_list_their_own_pending_review_but_others_cannot(): void
    {
        ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)->pending()->create([
            'body' => 'نظر در انتظار تأیید نویسنده.',
        ]);

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        Sanctum::actingAs($this->client);
        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews?mine=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.is_own', true)
            ->assertJsonPath('data.0.status', 'pending');

        $other = User::factory()->create();
        Sanctum::actingAs($other);
        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews?mine=1")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reviewer_identity_is_reduced_to_a_display_name(): void
    {
        ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)->create();

        $response = $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")->assertOk();

        $this->assertSame('سارا م.', $response->json('data.0.author'));
        $response->assertJsonMissing(['email' => $this->client->email]);
    }

    public function test_aggregates_appear_on_the_consultant_detail_endpoint(): void
    {
        ConsultantReview::factory()->forProfile($this->profile)->create(['rating' => 4, 'status' => 'approved']);

        $this->getJson("/api/v1/consultants/{$this->profile->id}")
            ->assertOk()
            ->assertJsonPath('data.avg_rating', 4)
            ->assertJsonPath('data.reviews_count', 1);
    }

    public function test_completed_appointment_is_required_to_review(): void
    {
        $appointment = Appointment::factory()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')
            ->confirmed()
            ->create();

        Sanctum::actingAs($this->client);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'body' => 'نظر آزمایشی برای نوبت تاییدنشده',
            ]),
            'appointment_id'
        );
    }

    public function test_a_client_cannot_review_someone_elses_appointment(): void
    {
        $intruder = User::factory()->create();
        $appointment = Appointment::factory()->completed()->for($this->profile, 'consultantProfile')->create();

        Sanctum::actingAs($intruder);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'body' => 'نظر آزمایشی برای نوبت دیگری',
            ]),
            'appointment_id'
        );
    }

    public function test_appointment_must_belong_to_the_consultant_in_the_route(): void
    {
        $otherProfile = ConsultantProfile::factory()->create(['is_verified' => true, 'is_active' => true]);
        $appointment = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($otherProfile, 'consultantProfile')
            ->create();

        Sanctum::actingAs($this->client);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
                'appointment_id' => $appointment->id,
                'rating' => 5,
                'body' => 'نظر آزمایشی برای مشاور نادرست',
            ]),
            'appointment_id'
        );
    }

    public function test_a_completed_appointment_can_be_reviewed_only_once(): void
    {
        $appointment = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')
            ->create();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
            'appointment_id' => $appointment->id,
            'rating' => 5,
            'body' => 'نظر نخست برای این نوبت.',
        ])->assertCreated()->assertJsonPath('data.rating', 5);

        Sanctum::actingAs($this->client);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
                'appointment_id' => $appointment->id,
                'rating' => 1,
                'body' => 'نظر تکراری برای همان نوبت.',
            ]),
            'appointment_id'
        );

        $this->assertDatabaseCount('consultant_reviews', 1);
    }

    public function test_new_reviews_stay_hidden_until_approved(): void
    {
        $appointment = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')
            ->create();

        Sanctum::actingAs($this->client);
        $reviewId = $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
            'appointment_id' => $appointment->id,
            'rating' => 4,
            'body' => 'منتظر تایید مدیر است.',
        ])->assertCreated()->json('data.id');

        $this->assertDatabaseHas('consultant_reviews', ['id' => $reviewId, 'status' => 'pending']);

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.reviews_count', 0);

        auth()->guard()->forgetUser();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);
        $this->putJson("/api/v1/admin/reviews/{$reviewId}", ['status' => 'approved'])->assertOk();

        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_editing_own_review_returns_it_to_moderation(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)
            ->create(['status' => 'approved', 'rating' => 5]);

        Sanctum::actingAs($this->client);
        $this->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 2,
            'body' => 'متن ویرایششده که باید دوباره بررسی شود.',
        ])->assertOk()->assertJsonPath('data.rating', 2);

        $this->assertDatabaseHas('consultant_reviews', [
            'id' => $review->id,
            'status' => 'pending',
        ]);
    }

    public function test_only_the_author_can_edit_a_review(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->create();

        Sanctum::actingAs($this->client);
        $this->putJson("/api/v1/reviews/{$review->id}", [
            'rating' => 1,
            'body' => 'تلاش برای ویرایش نظر دیگری.',
        ])->assertForbidden();
    }

    public function test_the_author_can_delete_their_review(): void
    {
        $review = ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)->create();

        Sanctum::actingAs($this->client);
        $this->deleteJson("/api/v1/reviews/{$review->id}")->assertOk();

        $this->assertDatabaseMissing('consultant_reviews', ['id' => $review->id]);
    }

    public function test_reviewable_appointments_only_list_unreviewed_completed_visits(): void
    {
        $reviewable = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')->create();
        $alreadyReviewed = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')->create();
        Appointment::factory()->confirmed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')->create();

        ConsultantReview::factory()->forProfile($this->profile)->byUser($this->client)
            ->create(['appointment_id' => $alreadyReviewed->id]);

        Sanctum::actingAs($this->client);
        $this->getJson("/api/v1/consultants/{$this->profile->id}/reviewable-appointments")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $reviewable->id);
    }

    public function test_review_submission_is_rate_limited(): void
    {
        Sanctum::actingAs($this->client);

        for ($i = 0; $i < 5; $i++) {
            $appointment = Appointment::factory()->completed()->for($this->client, 'client')
                ->for($this->profile, 'consultantProfile')->create();

            Sanctum::actingAs($this->client);
            $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
                'appointment_id' => $appointment->id,
                'rating' => 4,
                'body' => "نظر شماره {$i} برای آزمایش محدودیت نرخ.",
            ])->assertCreated();
        }

        $appointment = Appointment::factory()->completed()->for($this->client, 'client')
            ->for($this->profile, 'consultantProfile')->create();

        Sanctum::actingAs($this->client);
        $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
            'appointment_id' => $appointment->id,
            'rating' => 4,
            'body' => 'نظر ششم که باید محدود شود.',
        ])->assertStatus(429);
    }

    public function test_reviews_are_not_listed_for_non_discoverable_consultants(): void
    {
        $hidden = ConsultantProfile::factory()->create(['is_verified' => false, 'is_active' => true]);

        $this->getJson("/api/v1/consultants/{$hidden->id}/reviews")->assertNotFound();
    }

    public function test_guest_cannot_submit_a_review(): void
    {
        $this->postJson("/api/v1/consultants/{$this->profile->id}/reviews", [
            'appointment_id' => 1,
            'rating' => 5,
            'body' => 'نظر مهمان بدون ورود به حساب.',
        ])->assertUnauthorized();
    }
}
