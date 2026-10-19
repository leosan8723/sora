<?php

namespace Tests\Feature\Reviews;

use App\Models\ConsultantProfile;
use App\Models\ConsultantReview;
use App\Models\ReviewReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ReviewReportTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    private ConsultantProfile $profile;

    private User $client;

    private ConsultantReview $review;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = User::factory()->create(['name' => 'سارا محمدی']);
        $this->profile = ConsultantProfile::factory()
            ->for(User::factory()->consultant()->create())
            ->create(['is_verified' => true, 'is_active' => true]);

        $this->review = ConsultantReview::factory()->forProfile($this->profile)
            ->byUser($this->client)
            ->create(['status' => 'approved', 'rating' => 1, 'body' => 'نظر منتشرشده هدف گزارش.']);
    }

    public function test_a_user_can_report_a_published_review(): void
    {
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
            'reason' => 'این نظر محتوای نامناسبی دارد.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'open');

        $this->assertDatabaseHas('review_reports', [
            'review_id' => $this->review->id,
            'reporter_id' => $reporter->id,
            'status' => 'open',
        ]);
    }

    public function test_reporting_requires_authentication(): void
    {
        $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
            'reason' => 'گزارش مهمان بدون ورود.',
        ])->assertUnauthorized();
    }

    public function test_a_review_cannot_be_reported_once_per_user(): void
    {
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
            'reason' => 'گزارش نخست معتبر.',
        ])->assertCreated();

        Sanctum::actingAs($reporter);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
                'reason' => 'گزارش دوم همین کاربر.',
            ]),
            'review_id'
        );

        $this->assertSame(1, ReviewReport::query()->where('review_id', $this->review->id)->count());
    }

    public function test_the_review_author_cannot_report_their_own_review(): void
    {
        Sanctum::actingAs($this->client);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
                'reason' => 'نویسنده اجازه گزارش ندارد.',
            ]),
            'review_id'
        );
    }

    public function test_only_published_reviews_can_be_reported(): void
    {
        $pending = ConsultantReview::factory()->forProfile($this->profile)->pending()->create();
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/reviews/{$pending->id}/report", [
                'reason' => 'گزارش نظر منتشرنشده.',
            ]),
            'review_id'
        );
    }

    public function test_reporting_is_rate_limited(): void
    {
        $reporter = User::factory()->create();
        Sanctum::actingAs($reporter);

        // Fill the per-minute budget with plausible reasons against distinct
        // reviews; the unique constraint is per review/reporter pair.
        foreach (range(1, 5) as $i) {
            $review = ConsultantReview::factory()->forProfile($this->profile)
                ->byUser($this->client)->create(['status' => 'approved']);

            Sanctum::actingAs($reporter);
            $this->postJson("/api/v1/reviews/{$review->id}/report", [
                'reason' => "گزارش شماره {$i} برای آزمایش محدودیت نرخ.",
            ])->assertCreated();
        }

        Sanctum::actingAs($reporter);
        $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
            'reason' => 'گزارش ششم که باید محدود شود.',
        ])->assertStatus(429);
    }

    public function test_the_reason_is_required_and_bounded(): void
    {
        $reporter = User::factory()->create();

        Sanctum::actingAs($reporter);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/reviews/{$this->review->id}/report", []),
            'reason'
        );

        Sanctum::actingAs($reporter);
        $this->assertApiValidationError(
            $this->postJson("/api/v1/reviews/{$this->review->id}/report", [
                'reason' => str_repeat('ک', 501),
            ]),
            'reason'
        );
    }

    public function test_admins_triage_reports_without_touching_the_review(): void
    {
        $reporter = User::factory()->create();
        // A distinct reason lets the triage assertions match deterministic text.
        ReviewReport::factory()->for($this->review, 'review')->for($reporter, 'reporter')
            ->create(['reason' => 'این نظر محتوای نامناسبی دارد.']);

        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/review-reports?status=open')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reason', 'این نظر محتوای نامناسبی دارد.')
            ->assertJsonPath('data.0.reporter.name', $reporter->name)
            ->assertJsonPath('data.0.review.status', 'approved');

        $report = ReviewReport::query()->where('review_id', $this->review->id)->firstOrFail();
        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/review-reports/{$report->id}/resolve")
            ->assertOk()
            ->assertJsonPath('data.status', 'resolved');

        Sanctum::actingAs($admin);
        $this->postJson("/api/v1/admin/review-reports/{$report->id}/dismiss")
            ->assertOk()
            ->assertJsonPath('data.status', 'dismissed');

        // Triage must never mute the review itself.
        $this->assertDatabaseHas('consultant_reviews', [
            'id' => $this->review->id,
            'status' => 'approved',
        ]);
    }

    public function test_non_admins_cannot_reach_the_triage_queue(): void
    {
        $reporter = User::factory()->create();
        $report = ReviewReport::factory()->for($this->review, 'review')->for($reporter, 'reporter')->create();

        Sanctum::actingAs($reporter);
        $this->getJson('/api/v1/admin/review-reports')->assertForbidden();
        Sanctum::actingAs($reporter);
        $this->postJson("/api/v1/admin/review-reports/{$report->id}/resolve")->assertForbidden();
        Sanctum::actingAs($reporter);
        $this->postJson("/api/v1/admin/review-reports/{$report->id}/dismiss")->assertForbidden();
    }
}
