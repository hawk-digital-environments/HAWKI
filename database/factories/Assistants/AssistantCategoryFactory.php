<?php

declare(strict_types=1);

namespace Database\Factories\Assistants;

use App\Models\Assistants\AssistantCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantCategoryFactory extends Factory
{
    protected $model = AssistantCategory::class;

    public function definition(): array
    {
        return [
            'text' => fake()->unique()->word(),
        ];
    }
}
