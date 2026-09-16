<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Ai\AiTool;
use App\Services\Ai\Agents\Implementations\Chat\Values\ToolTransferData;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
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
        'capabilities',
    ];

    public function rules(): array
    {
        $rules = [
            'name' => ['string', 'max:255'],
            'handle' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-zA-Z0-9_-]+$/', // Mirrors handle detection in AiHandleStore in frontend
                'unique:assistants,handle' . $this->uniqueIdSuffix(),
                'unique:ai_models,label',
            ],
            'system_prompt' => ['string'],
            'greeting' => ['string'],
            'description' => ['string'],
            'detail_description' => ['string'],
            'allow_remix' => ['boolean'],
            'allow_model_select' => ['boolean'],
            'assistant_category' => ['nullable', JsonApiRule::toOne()],
            'model' => ['string'],
            'capabilities' => ['array', 'distinct', $this->capabilityEntryRule()],
            'max_tokens' => ['integer', 'min:0'],
            'temp' => ['numeric', 'min:0', 'max:1'],
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
     * Builds the rule validating every capabilities entry: a capability transfer
     * string ("capability:<key>:<native|auto|<tool-name>>") whose capability key is
     * declared in the {@see AiModelCapabilityRegistry} and whose concrete inner tool
     * (when one is named) matches an existing tool.
     *
     * Concrete tool selections without a capability belong to the assistant's
     * `ai_tools` relationship, not to this attribute.
     */
    private function capabilityEntryRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (!\is_array($value)) {
                return;
            }

            foreach ($value as $entry) {
                $this->validateCapabilityEntry($attribute, $entry, $fail);
            }
        };
    }

    private function validateCapabilityEntry(string $attribute, mixed $value, \Closure $fail): void
    {
        if (!\is_string($value) || $value === '') {
            $fail('The capabilities must be non-empty transfer strings.');

            return;
        }

        try {
            $transferData = ToolTransferData::fromString($value);
        } catch (\Throwable) {
            $fail('The capability string is not a valid tool transfer string.');

            return;
        }

        if (!$transferData->isCapability()) {
            $fail('The capabilities only accept capability transfer strings (capability:<key>:<native|auto|<tool>).');

            return;
        }

        $registry = app(AiModelCapabilityRegistry::class);

        if (!$registry->has($transferData->toolOrCapability)) {
            $fail('The capability references an unknown capability key.');

            return;
        }

        $innerTool = $transferData->innerTool;

        if ($innerTool !== null && $innerTool !== 'native' && $innerTool !== 'auto') {
            $toolExists = AiTool::query()
                ->where('name', $innerTool)
                ->exists();

            if (!$toolExists) {
                $fail('The capability references an unknown tool.');
            }
        }
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
