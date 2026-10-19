<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    public function test_a_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create(['email' => 'client@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'client@example.com',
            'password' => 'password',
            'device_name' => 'browser',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.email', 'client@example.com')
            ->assertJsonPath('data.user.role', 'client');

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_unknown_email_and_wrong_password_return_the_same_generic_error(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        $unknown = $this->postJson('/api/v1/auth/login', [
            'email' => 'nobody@example.com',
            'password' => 'wrong-password-1',
        ]);

        $wrong = $this->postJson('/api/v1/auth/login', [
            'email' => 'known@example.com',
            'password' => 'wrong-password-1',
        ]);

        $this->assertApiValidationError($unknown, 'email');
        $this->assertApiValidationError($wrong, 'email');

        // Identical responses for unknown and known accounts: no user enumeration.
        $this->assertSame($unknown->json('message'), $wrong->json('message'));
        $this->assertSame(
            $this->apiErrorMessage($unknown, 'email'),
            $this->apiErrorMessage($wrong, 'email')
        );
        $this->assertSame(__('auth.failed'), $this->apiErrorMessage($wrong, 'email'));

        $this->assertNull($unknown->json('data.token'));
    }

    public function test_login_with_malformed_payload_is_rejected(): void
    {
        $this->assertApiValidationError(
            $this->postJson('/api/v1/auth/login', ['password' => 'password']),
            'email'
        );
    }

    public function test_an_inactive_account_is_refused_after_the_password_is_verified(): void
    {
        $user = User::factory()->inactive()->create(['email' => 'blocked@example.com']);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'blocked@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(403)->assertJson(['message' => __('auth.inactive')]);
        $this->assertNull($response->json('data.token'));

        // A wrong password must still produce the generic error, so the
        // suspended state cannot be discovered without valid credentials.
        $this->assertApiValidationError(
            $this->postJson('/api/v1/auth/login', [
                'email' => 'blocked@example.com',
                'password' => 'still-wrong',
            ]),
            'email'
        );

        $this->assertTrue($user->fresh()->is_active === false);
    }

    public function test_login_attempts_are_rate_limited_per_account_and_ip(): void
    {
        $user = User::factory()->create(['email' => 'flood@example.com']);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->postJson('/api/v1/auth/login', [
                'email' => 'flood@example.com',
                'password' => 'wrong-password',
            ])->assertStatus(422);
        }

        $this->postJson('/api/v1/auth/login', [
            'email' => 'flood@example.com',
            'password' => 'wrong-password',
        ])->assertStatus(429);
    }
}
