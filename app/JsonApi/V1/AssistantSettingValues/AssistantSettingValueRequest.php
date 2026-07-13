<?php

declare(strict_types=1);

namespace App\JsonApi\V1\AssistantSettingValues;

use App\Models\Assistants\AssistantSetting;
use App\Models\Assistants\AssistantSettingValue;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantSettingValueRequest extends ResourceRequest
{
    public function rules(): array
    {
        $settingId = $this->isUpdating()
            ? $this->model()?->setting_id
            : $this->input('data.relationships.setting.data.id');

        $setting = is_numeric($settingId) ? AssistantSetting::find($settingId) : null;

        // A setting that explicitly lists an empty-string option (e.g. a "Default"
        // choice) accepts an empty value; empty then means "no contribution" in the
        // prompt composer. Settings without such an option must keep a concrete value.
        $allowsEmpty = null !== $setting
            && collect($setting->ui_options ?? [])
                ->contains(static fn (array $option): bool => '' === ($option['value'] ?? null));

        $valueAgainstSetting = static function (string $attribute, mixed $value, \Closure $fail) use ($setting): void {
            if (null === $value || '' === $value || null === $setting) {
                return;
            }

            $allowed = collect($setting->ui_options)->pluck('value')->all();

            if ([] !== $allowed && !\in_array($value, $allowed, true)) {
                $fail('Invalid value for this setting.');
            }
        };

        $valueRules = [$allowsEmpty ? 'present' : 'required', $valueAgainstSetting];

        if ($this->isUpdating()) {
            // On update only the value is mutable; assistant/setting are immutable.
            return [
                'value' => array_merge(['sometimes'], $valueRules),
            ];
        }

        return [
            'value' => $valueRules,
            'assistant' => ['required', JsonApiRule::toOne()],
            'setting' => [
                'required',
                JsonApiRule::toOne(),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $assistantId = $this->input('data.relationships.assistant.data.id');
                    $settingId = $value['id'] ?? null;

                    if (null === $assistantId || null === $settingId) {
                        return;
                    }

                    $conflict = AssistantSettingValue::query()
                        ->where('assistant_id', $assistantId)
                        ->where('setting_id', $settingId)
                        ->exists();

                    if ($conflict) {
                        $fail('A value for this setting already exists for the assistant.');
                    }
                },
            ],
        ];
    }
}
