<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            // Mirror the schema defaults so freshly created in-memory models
            // behave exactly like rows loaded from the database.
            'is_active' => true,
            'role' => UserRole::Client->value,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function role(string|UserRole $role): static
    {
        $value = $role instanceof UserRole ? $role->value : $role;

        return $this->state(fn (array $attributes) => [
            'role' => $value,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function client(): static
    {
        return $this->role(UserRole::Client);
    }

    public function consultant(): static
    {
        return $this->role(UserRole::Consultant);
    }

    public function admin(): static
    {
        return $this->role(UserRole::Admin);
    }
}
