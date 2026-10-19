<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\text;

/**
 * One-time secure setup for the initial administrator account.
 *
 * Security properties:
 * - Refuses to run once any administrator exists (setup is disabled).
 * - Never echoes the password to the console; it is provided interactively
 *   via a hidden prompt or as an operator-supplied option.
 * - Public registration can never grant this role; admins come only from
 *   this command or the database console.
 */
class CreateAdminCommand extends Command
{
    protected $signature = 'admin:create
        {--name= : Administrator display name}
        {--email= : Administrator login email}
        {--password= : Administrator password (hidden input is requested when omitted)}';

    protected $description = 'Create the initial administrator account (one-time; disabled once an admin exists)';

    public function handle(): int
    {
        if (User::query()->where('role', UserRole::Admin->value)->exists()) {
            $this->error('An administrator already exists. This one-time setup command is disabled.');

            return self::FAILURE;
        }

        $name = $this->option('name') ?: text(
            label: 'Administrator display name',
            required: true,
            maxLength: 120,
        );

        $email = $this->option('email') ?: text(
            label: 'Administrator email',
            required: true,
        );

        $password = $this->option('password');

        $validated = Validator::make(
            ['name' => $name, 'email' => $email, 'password' => $password],
            [
                'name' => ['required', 'string', 'max:120'],
                'email' => [
                    'required',
                    'email:rfc',
                    'max:254',
                    'unique:'.User::class.',email',
                ],
                // Use the provided password only for non-interactive
                // validation; interactive input happens below.
                'password' => ($password !== null && $password !== '')
                    ? $this->passwordRules()
                    : [],
            ],
        )->stopOnFirstFailure();

        if ($validated->fails()) {
            foreach ($validated->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $password ??= $this->secret('Administrator password');

        if (! is_string($password) || $password === '') {
            $this->error('A password is required.');

            return self::FAILURE;
        }

        $passwordErrors = Validator::make(
            ['password' => $password],
            ['password' => $this->passwordRules()],
        )->errors();

        if ($passwordErrors->isNotEmpty()) {
            foreach ($passwordErrors->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $user = User::query()->create([
            'name' => $name,
            'email' => $validated->valid()['email'],
            'password' => $password,
            'role' => UserRole::Admin,
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $this->info('Administrator created.');
        $this->line('Name:  '.$user->name);
        $this->line('Email: '.$user->email);
        $this->line('The password is not displayed. Store it in your password manager.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function passwordRules(): array
    {
        return ['required', Password::min(12)->letters()->mixedCase()->numbers()];
    }
}
