<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        return [
            'text' => fake()->unique()->word(),
        ];
    }
}
