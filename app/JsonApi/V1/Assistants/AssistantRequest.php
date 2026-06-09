<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;
use LaravelJsonApi\Validation\Rule as JsonApiRule;

class AssistantRequest extends ResourceRequest
{
    public function rules(): array
    {
        $rules = [
            'name' => ['nullable', 'string', 'max:255'],
            'handle' => [
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle',
                'unique:ai_models,label',
            ],
            'system_prompt' => ['nullable', 'string'],
            'greeting' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'detail_description' => ['nullable', 'string'],
            'allow_remix' => ['nullable', 'boolean'],
            'allow_model_select' => ['nullable', 'boolean'],
            'release_stage' => ['nullable', Rule::enum(ReleaseStage::class)],
            'formality' => ['nullable', 'string'],
            'category' => ['nullable', JsonApiRule::toOne()],
            'language' => ['nullable', JsonApiRule::toOne()],
            'model' => ['nullable', 'string'],
            'max_tokens' => ['nullable', 'integer', 'min:1'],
            'temp' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'top_p' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'user_prompts' => [JsonApiRule::toMany()],
            'ai_tools' => [JsonApiRule::toMany()],
            'tags' => [JsonApiRule::toMany()],
        ];

        if ($this->isUpdating()) {
            $assistant = $this->model();

            $rules['name'] = ['sometimes', 'string', 'max:255'];
            $rules['handle'] = [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle,'.$assistant?->id,
                'unique:ai_models,label',
            ];
            $rules['system_prompt'] = ['sometimes', 'nullable', 'string'];
            $rules['greeting'] = ['sometimes', 'nullable', 'string'];
            $rules['description'] = ['sometimes', 'nullable', 'string'];
            $rules['detail_description'] = ['sometimes', 'nullable', 'string'];
            $rules['allow_remix'] = ['sometimes', 'boolean'];
            $rules['allow_model_select'] = ['sometimes', 'boolean'];
            $rules['category'] = ['sometimes', JsonApiRule::toOne()];
            $rules['language'] = ['sometimes', JsonApiRule::toOne()];
            $rules['release_stage'] = ['sometimes', Rule::enum(ReleaseStage::class)];
            $rules['formality'] = ['sometimes', 'string'];
            $rules['model'] = ['sometimes', 'string'];
            $rules['max_tokens'] = ['sometimes', 'integer', 'min:1'];
            $rules['temp'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['top_p'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['user_prompts'] = ['sometimes', JsonApiRule::toMany()];
            $rules['ai_tools'] = ['sometimes', JsonApiRule::toMany()];
            $rules['tags'] = ['sometimes', JsonApiRule::toMany()];
            $rules['version_text'] = ['sometimes', 'nullable', 'string'];
        }

        return $rules;
    }
}
