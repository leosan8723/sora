<?php

namespace Tests\Feature\Admin;

use App\Models\Appointment;
use App\Models\ConsultantProfile;
use App\Models\ConsultationType;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Access control and management flows for the admin panel API.
 *
 * NOTE: this suite clears resolved auth guards after every request, so each
 * request in a multi-request test re-establishes Sanctum::actingAs.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();
    }

    private function actAsAdmin(): void
    {
        Sanctum::actingAs($this->admin);
    }

    public function test_only_admins_may_access_the_admin_api(): void
    {
        $this->getJson('/api/v1/admin/summary')->assertStatus(401);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/v1/admin/summary')->assertStatus(403);

        Sanctum::actingAs(User::factory()->consultant()->create());
        $this->getJson('/api/v1/admin/summary')->assertStatus(403);

        $this->actAsAdmin();
        $this->getJson('/api/v1/admin/summary')->assertStatus(200);
    }

    public function test_the_summary_reports_operational_counts(): void
    {
        // Three plain clients; the profile, appointment, and consultation-type
        // factories each spin up one more consultant user as a side effect.
        User::factory()->count(3)->create();
        ConsultantProfile::factory()->create();
        Appointment::factory()->confirmed()->create();

        $this->actAsAdmin();
        $response = $this->getJson('/api/v1/admin/summary');

        $response->assertStatus(200)
            ->assertJsonPath('users.clients', 4)
            ->assertJsonPath('users.consultants', 3)
            ->assertJsonPath('users.admins', 1)
            ->assertJsonPath('consultants.verified', 3)
            ->assertJsonPath('appointments.confirmed', 1);
    }

    public function test_admin_can_list_and_filter_users(): void
    {
        User::factory()->create(['name' => 'مريم رضایی', 'phone' => '09121112233']);
        User::factory()->consultant()->create();

        $this->actAsAdmin();
        $response = $this->getJson('/api/v1/admin/users?role=client');

        $response->assertStatus(200)->assertJsonCount(1, 'data');

        $this->actAsAdmin();
        $found = $this->getJson('/api/v1/admin/users?q=09121112233');
        $found->assertStatus(200)->assertJsonCount(1, 'data');
    }

    public function test_admin_can_create_a_consultant_with_a_profile(): void
    {
        $this->actAsAdmin();
        $response = $this->postJson('/api/v1/admin/users', [
            'name' => 'مشاور تازه',
            'email' => 'new-consultant@example.com',
            'password' => 'Password123',
            'role' => 'consultant',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.role', 'consultant')
            ->assertJsonPath('data.consultant.is_verified', false);

        $this->assertDatabaseHas('users', [
            'email' => 'new-consultant@example.com',
            'role' => 'consultant',
        ]);
        $this->assertDatabaseHas('consultant_profiles', [
            'user_id' => User::where('email', 'new-consultant@example.com')->value('id'),
        ]);
    }

    public function test_admin_cannot_lock_themselves_out(): void
    {
        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/users/{$this->admin->id}", [
            'role' => 'client',
        ])->assertStatus(422);

        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/users/{$this->admin->id}", [
            'is_active' => false,
        ])->assertStatus(422);

        $this->assertDatabaseHas('users', [
            'id' => $this->admin->id,
            'role' => 'admin',
            'is_active' => true,
        ]);
    }

    public function test_admin_can_deactivate_a_client_account(): void
    {
        $client = User::factory()->create();

        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/users/{$client->id}", [
            'is_active' => false,
        ])->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        $this->assertDatabaseHas('users', [
            'id' => $client->id,
            'is_active' => false,
        ]);
    }

    public function test_admin_can_verify_and_unverify_a_consultant(): void
    {
        $profile = ConsultantProfile::factory()->unverified()->create();

        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/consultants/{$profile->id}", [
            'is_verified' => true,
        ])->assertStatus(200)
            ->assertJsonPath('data.is_verified', true);

        $this->assertDatabaseHas('consultant_profiles', [
            'id' => $profile->id,
            'is_verified' => true,
        ]);

        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/consultants/{$profile->id}", [
            'is_verified' => false,
        ])->assertStatus(200);

        $this->assertDatabaseHas('consultant_profiles', [
            'id' => $profile->id,
            'is_verified' => false,
            'verified_at' => null,
        ]);
    }

    public function test_admin_can_list_all_appointments(): void
    {
        $confirmed = Appointment::factory()->confirmed()->create();
        Appointment::factory()->cancelled()->create();

        $this->actAsAdmin();
        $response = $this->getJson('/api/v1/admin/appointments?status=confirmed');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $confirmed->id);
    }

    public function test_admin_can_see_the_full_type_list(): void
    {
        ConsultationType::factory()->create();

        $this->actAsAdmin();
        $this->getJson('/api/v1/admin/consultation-types')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.mode', 'in_person');
    }

    public function test_admin_can_handle_contact_messages(): void
    {
        $message = ContactMessage::create([
            'name' => 'کاربر',
            'email' => 'user@example.com',
            'subject' => 'موضوع',
            'body' => 'متن پیام برای بررسی پنل مدیریت.',
        ]);

        $this->actAsAdmin();
        $this->getJson('/api/v1/admin/contact-messages?status=new')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data');

        $this->actAsAdmin();
        $this->putJson("/api/v1/admin/contact-messages/{$message->id}", [
            'status' => 'handled',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'handled');

        $this->actAsAdmin();
        $this->getJson('/api/v1/admin/contact-messages?status=new')
            ->assertJsonCount(0, 'data');
    }

    public function test_deactivated_admin_role_is_rejected(): void
    {
        $inactiveAdmin = User::factory()->admin()->inactive()->create();

        Sanctum::actingAs($inactiveAdmin);
        $this->getJson('/api/v1/admin/summary')->assertStatus(403);
    }

    public function test_deactivated_accounts_lose_api_access(): void
    {
        $user = User::factory()->create();

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertStatus(200);

        $user->update(['is_active' => false]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/auth/me')->assertStatus(403);

        Sanctum::actingAs($user);
        $this->postJson('/api/v1/appointments')->assertStatus(403);
    }
}
