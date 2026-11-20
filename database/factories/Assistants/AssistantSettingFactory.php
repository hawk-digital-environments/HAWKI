<?php

declare(strict_types=1);

namespace Database\Factories\Assistants;

use App\Models\Assistants\AssistantSetting;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantSettingFactory extends Factory
{
    protected $model = AssistantSetting::class;

    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(1),
            'label' => fake()->word(),
            'description' => fake()->sentence(),
            'ui_type' => 'select',
            'ui_options' => [
                ['value' => 'option1', 'label' => 'Option 1', 'prompt' => ''],
                ['value' => 'option2', 'label' => 'Option 2', 'prompt' => ''],
            ],
            'prompt_template' => null,
            'default_value' => 'option1',
        ];
    }
}
