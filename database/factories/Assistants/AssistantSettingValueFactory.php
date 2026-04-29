<?php

namespace Database\Factories\Assistants;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssistantSettingValueFactory extends Factory
{
    protected $model = AssistantSettingValue::class;

    public function definition(): array
    {
        return [
            'assistant_id' => Assistant::factory(),
            'setting_id' => AssistantSetting::factory(),
            'value' => fake()->word(),
        ];
    }
}
