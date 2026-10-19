<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Registered from the test so no placeholder route ships in the API.
        Route::middleware(['auth:sanctum', 'role:admin'])->get(
            '/api/v1/_test/admin-only',
            fn () => response()->json(['ok' => true])
        );
    }

    public function test_protected_endpoints_reject_guests(): void
    {
        $this->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->postJson('/api/v1/auth/logout')->assertStatus(401);
        $this->putJson('/api/v1/auth/profile', ['name' => 'Guest'])->assertStatus(401);
        $this->putJson('/api/v1/auth/password', [
            'current_password' => 'x',
            'password' => 'brandNew123',
            'password_confirmation' => 'brandNew123',
        ])->assertStatus(401);
    }

    public function test_only_an_admin_may_pass_the_role_middleware(): void
    {
        $this->getJson('/api/v1/_test/admin-only')->assertStatus(401);

        Sanctum::actingAs(User::factory()->client()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertStatus(403);

        Sanctum::actingAs(User::factory()->consultant()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertStatus(403);

        Sanctum::actingAs(User::factory()->admin()->create());
        $this->getJson('/api/v1/_test/admin-only')->assertOk()->assertJson(['ok' => true]);
    }

    public function test_role_gates_authorize_the_right_users(): void
    {
        $client = User::factory()->client()->create();
        $consultant = User::factory()->consultant()->create();
        $admin = User::factory()->admin()->create();

        $this->assertFalse(Gate::forUser($client)->allows('access-admin'));
        $this->assertTrue(Gate::forUser($admin)->allows('access-admin'));

        $this->assertFalse(Gate::forUser($client)->allows('access-consultant'));
        $this->assertTrue(Gate::forUser($consultant)->allows('access-consultant'));
        $this->assertTrue(Gate::forUser($admin)->allows('access-consultant'));

        $this->assertFalse(Gate::forUser($client)->check('access-consultant'));
    }

    public function test_me_exposes_only_the_authenticated_identity(): void
    {
        $owner = User::factory()->client()->create();
        $other = User::factory()->client()->create();

        $token = $owner->createToken('device')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/v1/auth/me');

        $response->assertOk()->assertJsonPath('data.id', $owner->id);
        $this->assertSame($other->email, User::find($other->id)->email);
        $this->assertNotSame($other->email, $response->json('data.email'));

        $data = $response->json('data');
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('remember_token', $data);
    }

    public function test_profile_update_cannot_change_privileged_attributes(): void
    {
        $user = User::factory()->client()->create();

        Sanctum::actingAs($user);

        $this->putJson('/api/v1/auth/profile', [
            'name' => 'Renamed Client',
            'phone' => '09123334455',
            'role' => 'admin',
            'is_active' => false,
            'id' => 999,
        ])->assertOk()->assertJsonPath('data.name', 'Renamed Client');

        $user->refresh();

        $this->assertSame('Renamed Client', $user->name);
        $this->assertSame('09123334455', $user->phone);
        $this->assertSame(UserRole::Client, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotSame(999, $user->id);
    }

    public function test_profile_update_validates_and_rejects_a_taken_phone(): void
    {
        User::factory()->create(['phone' => '09120009988']);
        $user = User::factory()->client()->create();

        Sanctum::actingAs($user);

        $response = $this->putJson('/api/v1/auth/profile', ['phone' => '09120009988']);

        $response->assertStatus(422);
        $this->assertSame(
            'phone',
            array_column($response->json('errors'), 'field')[0] ?? null
        );
    }

    public function test_unauthenticated_api_request_without_an_accept_header_returns_401_json(): void
    {
        // Regression: requests that do not advertise JSON expectations used to
        // fall through to the session login redirect and fail with
        // "Route [login] not defined" (HTTP 500) instead of 401.
        $response = $this->get('/api/v1/auth/me');

        $response->assertStatus(401);
        $this->assertSame('Unauthenticated.', $response->json('message'));
        $this->assertStringContainsString(
            'application/json',
            (string) $response->headers->get('Content-Type')
        );
    }

    public function test_logout_revokes_the_current_token(): void
    {
        $user = User::factory()->client()->create();
        $token = $user->createToken('device')->plainTextToken;

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();

        $this->withToken($token)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->assertSame(0, $user->tokens()->count());
    }
}
