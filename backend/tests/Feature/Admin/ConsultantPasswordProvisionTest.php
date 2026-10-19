<?php

namespace Tests\Feature\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Concerns\AssertsApiValidation;
use Tests\TestCase;

class ConsultantPasswordProvisionTest extends TestCase
{
    use AssertsApiValidation;
    use RefreshDatabase;

    private function actAsAdmin(): void
    {
        $this->actingAs($this->admin(), 'sanctum');
    }

    private function admin(): User
    {
        return User::factory()->role(UserRole::Admin)->create();
    }

    public function test_admin_can_provision_consultant_without_password_and_setup_link_works(): void
    {
        $this->actAsAdmin();

        // 1) Provision a consultant without a password.
        $created = $this->postJson('/api/v1/admin/users', [
            'name' => 'مشاور تازه',
            'email' => 'fresh-consultant@example.com',
            'role' => 'consultant',
        ])->assertStatus(201);

        $created->assertJsonPath('data.password_set', false);
        $created->assertJsonPath('data.consultant.is_verified', false);

        $userId = $created->json('data.id');

        // The account must not be usable until the setup link is completed.
        $this->postJson('/api/v1/auth/login', [
            'email' => 'fresh-consultant@example.com',
            'password' => 'WhateverGuessable1',
        ])->assertStatus(422);
        // 2) The admin mints a one-time setup link.
        $this->actingAs($this->admin(), 'sanctum');
        $link = $this->postJson("/api/v1/admin/users/{$userId}/password-link")
            ->assertStatus(200);

        $setupUrl = $link->json('data.setup_url');

        $this->assertStringContainsString('/password-setup?token=', $setupUrl);
        $this->assertStringContainsString('fresh-consultant%40example.com', $setupUrl);
        $this->assertSame(60, $link->json('data.expires_in_minutes'));

        // 3) Completing setup with the token works and revokes nothing else.
        parse_str(parse_url($setupUrl, PHP_URL_QUERY), $parts);
        $token = $parts['token'];

        $this->postJson('/api/v1/auth/password-setup', [
            'email' => 'fresh-consultant@example.com',
            'token' => $token,
            'password' => 'NewConsultantPass1A',
            'password_confirmation' => 'NewConsultantPass1A',
        ])->assertStatus(200)
            ->assertJsonPath('data.message', __('passwords.reset'));

        $user = User::query()->where('email', 'fresh-consultant@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('NewConsultantPass1A', $user->password));
        $this->assertNotNull($user->password_set_at);

        $this->postJson('/api/v1/auth/login', [
            'email' => 'fresh-consultant@example.com',
            'password' => 'NewConsultantPass1A',
        ])->assertStatus(200);

        // 4) Tokens are single-use.
        $this->postJson('/api/v1/auth/password-setup', [
            'email' => 'fresh-consultant@example.com',
            'token' => $token,
            'password' => 'SecondAttempt99Bb',
            'password_confirmation' => 'SecondAttempt99Bb',
        ])->assertStatus(422);
    }

    public function test_setup_fails_with_identical_response_for_unknown_email_and_bad_token(): void
    {
        $unknown = $this->postJson('/api/v1/auth/password-setup', [
            'email' => 'ghost@example.com',
            'token' => Str::random(60),
            'password' => 'UnknownGuest1Aa',
            'password_confirmation' => 'UnknownGuest1Aa',
        ])->assertStatus(422);

        $generic = $this->postJson('/api/v1/auth/password-setup', [
            'email' => 'ghost@example.com',
            'token' => 'definitely-not-a-token-aaaaaaaaaaaaa',
            'password' => 'UnknownGuest1Aa',
            'password_confirmation' => 'UnknownGuest1Aa',
        ])->assertStatus(422);

        $this->assertSame(
            $unknown->json('errors'),
            $generic->json('errors'),
        );
    }

    public function test_consultant_password_link_requires_admin_and_consultant_target(): void
    {
        $admin = User::factory()->role(UserRole::Admin)->create();
        $consultant = User::factory()->consultant()->create();
        $client = User::factory()->create();

        // Non-admin caller.
        $this->actingAs($consultant, 'sanctum');
        $this->postJson("/api/v1/admin/users/{$consultant->id}/password-link")->assertStatus(403);

        $this->actingAs($admin, 'sanctum');

        // Client target is not valid for consultant provisioning.
        $clientResponse = $this->postJson("/api/v1/admin/users/{$client->id}/password-link")
            ->assertStatus(422);

        $this->assertSame(__('admin.not_consultant'), $this->apiErrorMessage($clientResponse, 'user'));

        $consultant2 = User::factory()->consultant()->create();
        $this->actingAs($admin, 'sanctum');
        $this->postJson("/api/v1/admin/users/{$consultant2->id}/password-link")->assertStatus(200);
    }
}
