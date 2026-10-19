<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    public function test_a_user_can_change_password_with_a_correct_current_password(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'brandNew123',
            'password_confirmation' => 'brandNew123',
        ]);

        $response->assertNoContent();

        $user->refresh();
        $this->assertTrue(Hash::check('brandNew123', $user->password));
        $this->assertFalse(Hash::check('password', $user->password));
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/password', [
            'current_password' => 'not-my-password',
            'password' => 'brandNew123',
            'password_confirmation' => 'brandNew123',
        ]);

        $this->assertApiValidationError($response, 'current_password');

        $this->assertTrue(Hash::check('password', $user->fresh()->password));
    }

    public function test_reused_or_weak_new_password_is_rejected(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        Sanctum::actingAs($user);

        $this->assertApiValidationError(
            $this->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ]),
            'password'
        );

        Sanctum::actingAs($user);

        $this->assertApiValidationError(
            $this->putJson('/api/v1/auth/password', [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ]),
            'password'
        );
    }

    public function test_changing_password_revokes_every_other_token(): void
    {
        $user = User::factory()->create(['password' => 'password']);

        $current = $user->createToken('current-device')->plainTextToken;
        $other = $user->createToken('other-device')->plainTextToken;

        $this->withToken($current)->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'brandNew123',
            'password_confirmation' => 'brandNew123',
        ])->assertNoContent();

        $this->withToken($current)->getJson('/api/v1/auth/me')->assertOk();

        $this->withToken($other)->getJson('/api/v1/auth/me')->assertStatus(401);

        $this->assertSame(1, $user->tokens()->count());
    }

    public function test_password_change_requires_authentication(): void
    {
        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'password',
            'password' => 'brandNew123',
            'password_confirmation' => 'brandNew123',
        ])->assertStatus(401);
    }
}
