<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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
            'full_name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'Staff',
            'is_active' => true,
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

    /**
     * Keep the spatie role in sync with the `role` string column so
     * factory-built users satisfy spatie-based role checks (e.g. the
     * role.min middleware, which reads the assigned spatie role).
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if (! empty($user->role)) {
                $role = Role::firstOrCreate(
                    ['name' => $user->role, 'guard_name' => 'web'],
                );
                $user->assignRole($role);
            }
        });
    }
}
