<?php

declare(strict_types=1);

namespace Database\Factories\Assistants;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantAvatarFactory extends Factory
{
    private const EMOJIS = ['🎓', '📚', '💡', '🧪', '🏫', '🎯', '🔬', '🌐'];
    private const GRADIENTS = [
        'background: hsl(228 80% 52%); color: hsl(205 100% 88%)',
        'background: hsl(345 75% 66%); color: hsl(340 65% 18%)',
        'background: hsl(45 98% 60%); color: hsl(355 75% 18%)',
        'background: hsl(155 100% 19%); color: hsl(72 90% 62%)',
        'background: hsl(205 90% 84%); color: hsl(225 70% 25%)',
        'background: hsl(330 85% 90%); color: hsl(340 60% 22%)',
    ];
    protected $model = AssistantAvatar::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::EMOJIS),
            'icon_css' => fake()->randomElement(self::GRADIENTS),
            'assistant_id' => Assistant::factory(),
        ];
    }

    /**
     * assistant_id is intentionally not mass-assignable on the model.
     */
    public function newModel(array $attributes = []): AssistantAvatar
    {
        return AssistantAvatar::unguarded(static fn () => new AssistantAvatar($attributes));
    }
}
