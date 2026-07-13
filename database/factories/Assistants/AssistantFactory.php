<?php

declare(strict_types=1);

namespace Database\Factories\Assistants;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantCategory;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assistant>
 */
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
            'category_id' => AssistantCategory::factory(),
            'release_stage' => 'private',
            'requested_release_stage' => null,
            'model' => 'gpt-4',
            'max_tokens' => fake()->numberBetween(100, 4096),
            'temp' => fake()->randomFloat(2, 0, 1),
            'top_p' => fake()->randomFloat(2, 0, 1),
            'creator_id' => User::factory(),
            'remixed_creator_id' => null,
            'remixed_assistant_id' => null,
            'organization_id' => static fn () => Organization::first()?->id,
        ];
    }

    /**
     * Sensitive fields (creator_id, organization_id, release_stage, ...) are
     * intentionally not mass-assignable on the model. The factory builds
     * representative instances with those fields populated, so it constructs
     * the model under Eloquent's unguarded mode.
     */
    public function newModel(array $attributes = []): Assistant
    {
        return Assistant::unguarded(static fn () => new Assistant($attributes));
    }

    public function configure(): static
    {
        return $this->afterCreating(static function (Assistant $assistant): void {
            if (!$assistant->assistantVersions()->exists()) {
                $assistant->assistantVersions()->save((new \App\Models\Assistants\AssistantVersion())->forceFill([
                    'text' => json_encode(['changes' => []], \JSON_THROW_ON_ERROR),
                    'version' => 1.0,
                ]),);
            }
        });
    }
}
