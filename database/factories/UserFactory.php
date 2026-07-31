<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
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
            'username' => fake()->unique()->userName(),
            'employeetype' => fake()->randomElement(['full-time', 'part-time', 'contractor']),
            'publicKey' => '',
            'avatar_id' => null,
            'bio' => fake()->text(),
            'created_at' => now(),
            'updated_at' => now(),
            'isRemoved' => false,
        ];
    }
}
