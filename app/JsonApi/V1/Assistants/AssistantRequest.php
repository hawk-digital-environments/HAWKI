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
            'name' => ['string', 'max:255'],
            'handle' => [
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle',
                'unique:ai_models,label',
            ],
            'system_prompt' => ['string'],
            'greeting' => ['string'],
            'description' => ['string'],
            'detail_description' => [ 'string'],
            'allow_remix' => [ 'boolean'],
            'allow_model_select' => [ 'boolean'],
            'release_stage' => [ Rule::enum(ReleaseStage::class)],
            'category' => ['nullable', JsonApiRule::toOne()],
            'model' => ['string'],
            'max_tokens' => ['integer', 'min:0'],
            'temp' => ['numeric', 'min:0', 'max:1'],
            'top_p' => ['numeric', 'min:0', 'max:1'],
            'ai_tools' => [JsonApiRule::toMany()],
            'assistant_tags' => [JsonApiRule::toMany()],
            'shared_users' => [JsonApiRule::toMany()],
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
            $rules['release_stage'] = ['sometimes', Rule::enum(ReleaseStage::class)];
            $rules['model'] = ['sometimes', 'string'];
            $rules['max_tokens'] = ['sometimes', 'integer', 'min:0'];
            $rules['temp'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['top_p'] = ['sometimes', 'numeric', 'min:0', 'max:1'];
            $rules['ai_tools'] = ['sometimes', JsonApiRule::toMany()];
            $rules['assistant_tags'] = ['sometimes', JsonApiRule::toMany()];
            $rules['shared_users'] = ['sometimes', JsonApiRule::toMany()];
        }

        return $rules;
    }
}
