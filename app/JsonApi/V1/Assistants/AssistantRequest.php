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
            'name' => ['required', 'string', 'max:255'],
            'handle' => [
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle',
                'unique:ai_models,label',
            ],
            'system_prompt' => ['required', 'string'],
            'greeting' => ['required', 'string'],
            'description' => ['required', 'string'],
            'detail_description' => ['required', 'string'],
            'allow_remix' => ['required', 'boolean'],
            'allow_model_select' => ['required', 'boolean'],
            'release_stage' => ['required', Rule::enum(ReleaseStage::class)],
            'formality' => ['required', 'string'],
            'category' => ['required', JsonApiRule::toOne()],
            'language' => ['required', JsonApiRule::toOne()],
            'model' => ['required', 'string'],
            'model_length' => ['required', 'integer', 'min:1'],
            'model_temp' => ['required', 'numeric', 'min:0', 'max:1'],
            'model_top_p' => ['required', 'numeric', 'min:0', 'max:1'],
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
                'unique:assistants,handle,' . $assistant?->id,
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
            $rules['model_length'] = ['sometimes', 'integer', 'min:1'];
            $rules['model_temp'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['model_top_p'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['user_prompts'] = ['sometimes', JsonApiRule::toMany()];
            $rules['ai_tools'] = ['sometimes', JsonApiRule::toMany()];
            $rules['tags'] = ['sometimes', JsonApiRule::toMany()];
            $rules['version_text'] = ['sometimes', 'nullable', 'string'];
        }

        return $rules;
    }
}
