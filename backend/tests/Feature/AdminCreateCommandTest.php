<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCreateCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_initial_admin_with_hashed_password_and_does_not_echo_password(): void
    {
        $password = 'Str0ngSetuPPass!9';

        $this->artisan('admin:create', [
            '--name' => 'Root Admin',
            '--email' => 'root-admin@example.com',
            '--password' => $password,
        ])->assertExitCode(0);

        $user = User::query()->where('email', 'root-admin@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue((bool) $user->is_active);
        $this->assertNotSame($password, $user->password);
        $this->assertDatabaseHas('users', ['email' => 'root-admin@example.com', 'role' => 'admin']);
    }

    public function test_refuses_to_run_once_an_administrator_exists(): void
    {
        User::factory()->role(UserRole::Admin)->create();

        $before = User::query()->where('role', UserRole::Admin->value)->count();

        $this->artisan('admin:create', [
            '--name' => 'Second Admin',
            '--email' => 'second-admin@example.com',
            '--password' => 'AnotherStr0ngPass7',
        ])->assertExitCode(1);

        $this->assertSame($before, User::query()->where('role', UserRole::Admin->value)->count());
    }

    public function test_rejects_duplicate_email_and_weak_password(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $this->artisan('admin:create', [
            '--name' => 'Dup Admin',
            '--email' => 'taken@example.com',
            '--password' => 'Str0ngPassphrase12',
        ])->assertExitCode(1);

        $this->artisan('admin:create', [
            '--name' => 'Weak Admin',
            '--email' => 'weak-admin@example.com',
            '--password' => 'onlysmallpass',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'weak-admin@example.com']);

        User::factory()->role(UserRole::Admin)->create();

        $this->artisan('admin:create', [
            '--name' => 'Late Admin',
            '--email' => 'late-admin@example.com',
            '--password' => 'Str0ngPassphrase12',
        ])->assertExitCode(1);

        $this->assertDatabaseMissing('users', ['email' => 'late-admin@example.com']);
    }
}
