<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\Category;
use App\Models\Assistants\Language;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantFactory extends Factory
{
    protected $model = Assistant::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'handle' => fake()->unique()->slug(3),
            'system_prompt' => fake()->sentence(),
            'greeting' => fake()->sentence(),
            'description' => fake()->sentence(),
            'detail_description' => fake()->paragraph(),
            'allow_remix' => fake()->boolean(),
            'allow_model_select' => fake()->boolean(),
            'language_id' => Language::factory(),
            'category_id' => Category::factory(),
            'release_stage' => 'private',
            'formality' => 'neutral',
            'model' => 'gpt-4',
            'max_tokens' => fake()->numberBetween(100, 4096),
            'temp' => fake()->randomFloat(2, 0, 1),
            'top_p' => fake()->randomFloat(2, 0, 1),
            'creator_id' => User::factory(),
            'remixed_creator_id' => User::factory(),
            'organization_id' => fn () => Organization::first()?->id,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Assistant $assistant) {
            $assistant->versions()->create([
                'text' => 'Initial version',
                'version' => 1.0,
            ]);
        });
    }
}
