<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AiAssistant>
 */
class AiAssistantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => $this->faker->unique()->slug(2),
            'name' => $this->faker->words(2, true),
            'description' => $this->faker->sentence(),
            'status' => $this->faker->randomElement(['draft', 'active', 'archived']),
            'visibility' => $this->faker->randomElement(['private', 'org', 'public']),
            'org_id' => $this->faker->optional()->uuid(),
            'owner_id' => \App\Models\User::factory(),
            'ai_model' => $this->faker->optional()->uuid(),
            'prompt' => $this->faker->optional()->word(),
            'tools' => $this->faker->optional()->randomElements(['search', 'calculator', 'weather'], 2),
        ];
    }

    /**
     * Indicate that the assistant is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the assistant is public.
     */
    public function public(): static
    {
        return $this->state(fn (array $attributes) => [
            'visibility' => 'public',
        ]);
    }

    /**
     * Indicate that the assistant is a draft.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }
}
