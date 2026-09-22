<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Columns added by extend_users_table. The database defaults them, but a
            // freshly created model does not hold what it did not insert, and
            // Model::shouldBeStrict() makes reading one an exception rather than null.
            'locale' => 'en',
            'numeral_system' => 'latn',
            'failed_attempts' => 0,
            'locked_until' => null,
            'is_active' => true,
            'is_service_account' => false,
            'mfa_confirmed_at' => null,
            'app_authentication_secret' => null,
            'app_authentication_recovery_codes' => null,
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
}
