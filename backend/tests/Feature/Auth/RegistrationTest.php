<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use AssertsApiValidation, RefreshDatabase;

    public function test_a_client_can_register_and_receives_a_token(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'سارا محمدی',
            'email' => 'sara@example.org',
            'phone' => '09120000001',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.token_type', 'Bearer')
            ->assertJsonPath('data.user.role', 'client')
            ->assertJsonPath('data.user.email', 'sara@example.org')
            ->assertJsonPath('data.user.name', 'سارا محمدی');

        $this->assertNotEmpty($response->json('data.token'));

        $user = User::where('email', 'sara@example.org')->firstOrFail();

        $this->assertSame(UserRole::Client, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('secret1234', $user->password));
        $this->assertNotNull($user->tokens()->first());
    }

    public function test_registration_cannot_escalate_role_or_force_deactivation(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Attacker',
            'email' => 'attacker@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'role' => 'admin',
            'is_active' => false,
        ])->assertStatus(201);

        $user = User::where('email', 'attacker@example.com')->firstOrFail();

        $this->assertSame(UserRole::Client, $user->role);
        $this->assertTrue($user->is_active);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Duplicate',
            'email' => 'taken@example.com',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
        ]);

        $this->assertApiValidationError($response, 'email');
        $this->assertSame(1, User::where('email', 'taken@example.com')->count());
    }

    public function test_invalid_payload_is_rejected(): void
    {
        $this->assertApiValidationError(
            $this->postJson('/api/v1/auth/register', [
                'name' => '',
                'email' => 'not-an-email',
                'password' => 'short',
                'password_confirmation' => 'mismatch',
            ]),
            'name', 'email', 'password'
        );

        $this->assertSame(0, User::count());
    }

    public function test_duplicate_phone_is_rejected(): void
    {
        User::factory()->create(['phone' => '09121112233']);

        $this->assertApiValidationError(
            $this->postJson('/api/v1/auth/register', [
                'name' => 'Phone Clone',
                'email' => 'phone-clone@example.com',
                'phone' => '09121112233',
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
            ]),
            'phone'
        );
    }
}
