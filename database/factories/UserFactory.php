<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'bp_code' => 'S' . fake()->unique()->numberBetween(1000, 9999),
            'name' => fake()->name(),
            'role' => fake()->numberBetween(1, 3),
            'status' => 1,
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'email' => fake()->unique()->safeEmail(),
        ];
    }

    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 1,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a finance user.
     */
    public function finance(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 2,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is a supplier.
     */
    public function supplier(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 3,
            'status' => 1,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 0,
        ]);
    }
}
