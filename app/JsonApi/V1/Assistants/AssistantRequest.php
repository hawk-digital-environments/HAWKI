<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Validation\Rule;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

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
            'category' => ['required', 'array:type,id'],
            'category.type' => ['required', 'in:categories'],
            'category.id' => ['required', 'integer', 'exists:categories,id'],
            'language' => ['required', 'array:type,id'],
            'language.type' => ['required', 'in:languages'],
            'language.id' => ['required', 'integer', 'exists:languages,id'],
            'model' => ['required', 'string'],
            'model_length' => ['required', 'integer', 'min:1'],
            'model_temp' => ['required', 'numeric', 'min:0', 'max:1'],
            'model_top_p' => ['required', 'numeric', 'min:0', 'max:1'],
            'user_prompts' => ['array'],
            'user_prompts.*.id' => ['required', 'integer', 'exists:user_prompts,id'],
            'ai_tools' => ['array'],
            'ai_tools.*.id' => ['required', 'integer', 'exists:ai_tools,id'],
            'tags' => ['array'],
            'tags.*.id' => ['required', 'integer', 'exists:tags,id'],
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
            $rules['category'] = ['sometimes', 'array:type,id'];
            $rules['category.type'] = ['sometimes', 'in:categories'];
            $rules['category.id'] = ['sometimes', 'integer', 'exists:categories,id'];
            $rules['language'] = ['sometimes', 'array:type,id'];
            $rules['language.type'] = ['sometimes', 'in:languages'];
            $rules['language.id'] = ['sometimes', 'integer', 'exists:languages,id'];
            $rules['release_stage'] = ['sometimes', Rule::enum(ReleaseStage::class)];
            $rules['formality'] = ['sometimes', 'string'];
            $rules['model'] = ['sometimes', 'string'];
            $rules['model_length'] = ['sometimes', 'integer', 'min:1'];
            $rules['model_temp'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['model_top_p'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['user_prompts'] = ['sometimes', 'array'];
            $rules['ai_tools'] = ['sometimes', 'array'];
            $rules['tags'] = ['sometimes', 'array'];
            $rules['version_text'] = ['sometimes', 'nullable', 'string'];
        }

        return $rules;
    }
}
