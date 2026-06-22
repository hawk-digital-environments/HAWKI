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
            'data.attributes.settings.*.setting_id' => ['required', 'integer'],
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

            $ids = array_filter(array_map(fn ($entry) => $entry['setting_id'] ?? null, $settings));
            $catalog = AssistantSetting::whereIn('id', $ids)->get()->keyBy('id');

            $seenIds = [];

            foreach ($settings as $index => $entry) {
                $settingId = $entry['setting_id'] ?? null;

                if ($settingId === null) {
                    continue;
                }

                $pointer = "data.attributes.settings.{$index}.setting_id";

                if (isset($seenIds[$settingId])) {
                    $validator->errors()->add($pointer, "Duplicate setting '{$settingId}'.");
                    continue;
                }
                $seenIds[$settingId] = true;

                $setting = $catalog->get($settingId);

                if ($setting === null) {
                    $validator->errors()->add($pointer, "The setting '{$settingId}' does not exist.");
                    continue;
                }

                $allowed = collect($setting->ui_options)->pluck('value')->all();

                if ($allowed !== [] && ! in_array($entry['value'] ?? null, $allowed, true)) {
                    $validator->errors()->add(
                        "data.attributes.settings.{$index}.value",
                        "Invalid value for setting '{$settingId}'."
                    );
                }
            }
        });
    }
}
