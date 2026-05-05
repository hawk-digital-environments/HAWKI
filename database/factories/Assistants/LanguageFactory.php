<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\Language;
use Illuminate\Database\Eloquent\Factories\Factory;

class LanguageFactory extends Factory
{
    protected $model = Language::class;

    public function definition(): array
    {
        return [
            'text' => fake()->unique()->languageCode(),
        ];
    }
}
