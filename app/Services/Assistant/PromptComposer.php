<?php

declare(strict_types=1);

namespace App\Services\Assistant;

use App\Models\Assistants\Assistant;
use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use Illuminate\Support\Collection;

class PromptComposer
{
    public function compose(Assistant $assistant): string
    {
        $prompt = $assistant->system_prompt ?? '';

        $values = $assistant->settingValues()->with('setting')->get();

        $effectiveValues = $this->resolveEffectiveValues($values);

        foreach ($effectiveValues as $entry) {
            $fragment = $this->resolveFragment($entry['setting'], $entry['value']);
            if ($fragment !== '') {
                $prompt .= "\n\n".$fragment;
            }
        }

        return $prompt;
    }

    /**
     * @param  Collection<int, AssistantSettingValue>  $values
     * @return array<int, array{setting: AssistantSetting, value: mixed}>
     */
    private function resolveEffectiveValues($values): array
    {
        $result = [];

        foreach ($values as $valueRecord) {
            $setting = $valueRecord->setting;
            if ($setting === null) {
                continue;
            }

            $value = $valueRecord->value ?? $setting->default_value;
            if ($value === null || $value === '') {
                continue;
            }

            $result[] = ['setting' => $setting, 'value' => $value];
        }

        return $result;
    }

    private function resolveFragment(AssistantSetting $setting, mixed $value): string
    {
        if ($setting->ui_type === 'select') {
            foreach ($setting->ui_options ?? [] as $option) {
                if ($option['value'] === $value) {
                    if (! empty($option['prompt'])) {
                        return $option['prompt'];
                    }
                    break;
                }
            }
        }

        if ($setting->prompt_template !== null) {
            $displayValue = is_array($value) ? implode(', ', $value) : (string) $value;

            return str_replace('{{value}}', $displayValue, $setting->prompt_template);
        }

        return '';
    }
}
