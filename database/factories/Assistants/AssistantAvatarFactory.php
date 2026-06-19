<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\AssistantAvatar;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class AssistantAvatarFactory extends Factory
{
    protected $model = AssistantAvatar::class;

    public function definition(): array
    {
        return [
            'uuid' => Str::uuid(),
            'name' => fake()->unique()->word(),
        ];
    }
}
