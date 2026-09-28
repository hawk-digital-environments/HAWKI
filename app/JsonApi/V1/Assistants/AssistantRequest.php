<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Values\AssistantFieldRules;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantRequest extends ResourceRequest
{
    /**
     * Fields whose rules are identical between create and update except for the
     * "sometimes" presence modifier added on update.
     */
    private const UPDATE_NULLABLE_FIELDS = [
        'system_prompt',
        'greeting',
        'description',
        'detail_description',
    ];

    public function rules(): array
    {
        $rules = [
            'name' => AssistantFieldRules::name(),
            'handle' => [
                'nullable',
                ...AssistantFieldRules::handle(),
                'unique:assistants,handle' . $this->uniqueIdSuffix(),
                'unique:ai_models,label',
            ],
            'system_prompt' => AssistantFieldRules::text(),
            'greeting' => AssistantFieldRules::text(),
            'description' => AssistantFieldRules::text(),
            'detail_description' => AssistantFieldRules::text(),
            'allow_remix' => ['boolean'],
            'allow_model_select' => ['boolean'],
            'assistant_category' => ['nullable', JsonApiRule::toOne()],
            'model' => AssistantFieldRules::text(),
            'max_tokens' => ['integer', 'min:0'],
            'temp' => ['numeric', 'min:0', 'max:2'],
            'top_p' => ['numeric', 'min:0', 'max:1'],
            'ai_tools' => [JsonApiRule::toMany()],
            'assistant_tags' => [JsonApiRule::toMany()],
            'shared_users' => [JsonApiRule::toMany()],
        ];

        if (! $this->isUpdating()) {
            return $rules;
        }

        foreach ($rules as $field => $fieldRules) {
            $prepend = ['sometimes'];
            if (\in_array($field, self::UPDATE_NULLABLE_FIELDS, true)) {
                $prepend[] = 'nullable';
            }
            $rules[$field] = array_merge($prepend, $fieldRules);
        }

        return $rules;
    }

    /**
     * Excludes the current model from the handle uniqueness check on update so an
     * assistant can keep its own handle. Returns an empty string on create.
     */
    private function uniqueIdSuffix(): string
    {
        return $this->isUpdating()
            ? ',' . $this->model()?->id
            : '';
    }
}
