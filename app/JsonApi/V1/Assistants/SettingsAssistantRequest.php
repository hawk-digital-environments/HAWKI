<?php

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\AssistantSetting;
use Illuminate\Foundation\Http\FormRequest;

class SettingsAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.attributes.settings' => ['required', 'array', 'min:1'],
            'data.attributes.settings.*.key' => ['required', 'string'],
            'data.attributes.settings.*.value' => ['required'],
        ];
    }

    protected function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $settings = $this->input('data.attributes.settings', []);

            if (! is_array($settings)) {
                return;
            }

            $keys = array_filter(array_map(fn ($entry) => $entry['key'] ?? null, $settings));
            $catalog = AssistantSetting::whereIn('key', $keys)->get()->keyBy('key');

            $seenKeys = [];

            foreach ($settings as $index => $entry) {
                $key = $entry['key'] ?? null;

                if ($key === null) {
                    continue;
                }

                $pointer = "data.attributes.settings.{$index}.key";

                if (isset($seenKeys[$key])) {
                    $validator->errors()->add($pointer, "Duplicate setting '{$key}'.");
                    continue;
                }
                $seenKeys[$key] = true;

                $setting = $catalog->get($key);

                if ($setting === null) {
                    $validator->errors()->add($pointer, "The setting '{$key}' does not exist.");
                    continue;
                }

                $allowed = collect($setting->ui_options)->pluck('value')->all();

                if ($allowed !== [] && ! in_array($entry['value'] ?? null, $allowed, true)) {
                    $validator->errors()->add(
                        "data.attributes.settings.{$index}.value",
                        "Invalid value for setting '{$key}'."
                    );
                }
            }
        });
    }
}
