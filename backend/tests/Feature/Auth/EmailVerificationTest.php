<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Registration → emailed single-use link → verify → replay rejected.
     * The token is recovered from the notification, mirroring the log-driver
     * delivery used locally (the URL is written to laravel.log there).
     */
    public function test_registration_issues_verifiable_single_use_link(): void
    {
        Notification::fake();

        $registration = $this->postJson('/api/v1/auth/register', [
            'name' => 'negar verification',
            'email' => 'negar-verify@example.com',
            'password' => 'Unguessable-8Kx-2026',
            'password_confirmation' => 'Unguessable-8Kx-2026',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['email' => 'negar-verify@example.com']);
        $user = User::where('email', 'negar-verify@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertFalse((bool) $registration->json('data.user.email_verified'));

        // The account token is NOT yet usable for anything that trusts us;
        // the verification state only flips via the emailed link.
        $this->assertDatabaseHas('email_verification_tokens', ['user_id' => $user->id]);

        Notification::assertSentTo($user, VerifyEmailNotification::class);

        $query = $this->queryOf($user);

        $this->get('/api/v1/auth/email/verify?'.http_build_query($query))
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);
        $this->assertNotNull($user->fresh()->email_verified_at);

        // Single use: replaying the exact same link is rejected.
        $this->get('/api/v1/auth/email/verify?'.http_build_query($query))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'token');
    }

    public function test_bogus_token_returns_the_generic_localized_error(): void
    {
        $user = User::factory()->unverified()->create();

        $this->get('/api/v1/auth/email/verify?'.http_build_query([
            'id' => $user->id,
            'token' => 'not-a-real-token',
            'expires' => now()->addMinutes(50)->getTimestamp(),
        ]))
            ->assertStatus(422)
            ->assertJsonPath('errors.0.field', 'token');
    }

    public function test_resend_is_throttled_and_generic(): void
    {
        Notification::fake();

        $user = User::factory()->unverified()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend')->assertOk();
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/v1/auth/email/resend')->assertOk();
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/v1/auth/email/resend')->assertOk();

        // 3/minute: the 4th attempt is throttled.
        $this->actingAs($user, 'sanctum');
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(429);

        Notification::assertSentTo($user, VerifyEmailNotification::class, 3);
    }

    public function test_resend_for_verified_user_is_idempotent(): void
    {
        Notification::fake();

        $user = User::factory()->create(); // factory default: verified

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/auth/email/resend')
            ->assertOk()
            ->assertJsonPath('data.email_verified', true);

        Notification::assertNothingSent();
    }

    public function test_unauthenticated_resend_is_rejected(): void
    {
        $this->postJson('/api/v1/auth/email/resend')->assertStatus(401);
    }

    private function queryOf(User $user): array
    {
        Notification::assertSentTo($user, VerifyEmailNotification::class);

        // Recover the exact link the client received (the log driver prints
        // the same URL, which is how the local E2E flow works).
        $sent = Notification::sent($user, VerifyEmailNotification::class)->first();

        parse_str((string) parse_url($sent->verifyUrl, PHP_URL_QUERY), $query);

        return $query;
    }
}
