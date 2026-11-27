<?php

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
            'email' => fake()->unique()->userName().'@'.config('tua.email_domain'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'user_type' => 'student',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /** Registered but has not opened the activation link yet. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
            'is_active' => false,
        ]);
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
