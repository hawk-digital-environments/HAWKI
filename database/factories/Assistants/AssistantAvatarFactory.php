<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantAvatar;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantAvatarFactory extends Factory
{
    protected $model = AssistantAvatar::class;

    private const EMOJIS = ['🎓', '📚', '💡', '🧪', '🏫', '🎯', '🔬', '🌐'];

    private const GRADIENTS = [
        'background: linear-gradient(135deg, rgb(73,66,215), rgb(101,34,195));',
        'background: linear-gradient(135deg, rgb(219,39,119), rgb(225,29,72));',
        'background: linear-gradient(135deg, rgb(245,158,11), rgb(234,88,12));',
        'background: linear-gradient(135deg, rgb(16,185,129), rgb(5,150,105));',
        'background: linear-gradient(135deg, rgb(59,130,246), rgb(37,99,235));',
        'background: linear-gradient(135deg, rgb(239,68,68), rgb(220,38,38));',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(self::EMOJIS),
            'icon_css' => fake()->randomElement(self::GRADIENTS),
            'assistant_id' => Assistant::factory(),
        ];
    }
}
