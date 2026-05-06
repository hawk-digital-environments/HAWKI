<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Services\Assistant\Values\ReleaseStage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'handle' => [
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle',
                'unique:ai_models,label',
            ],
            'system_prompt' => 'required|string',
            'greeting' => 'required|string',
            'description' => 'required|string',
            'detail_description' => 'required|string',
            'allow_remix' => 'required|boolean',
            'allow_model_select' => 'required|boolean',
            'language' => 'required|string',
            'release_stage' => ['required', Rule::enum(ReleaseStage::class)],
            'formality' => 'required|string',
            'category' => 'required|string',
            'model' => 'required|string',
            'model_length' => 'required|integer|min:1',
            'model_temp' => 'required|numeric|min:0|max:1',
            'model_top_p' => 'required|numeric|min:0|max:1',
            'user_prompts' => 'array',
            'user_prompts.*.text' => 'required|string',
            'ai_tools' => 'array',
            'ai_tools.*.id' => 'required|exists:ai_tools,id',
            'tags' => ['array'],
            'tags.*' => ['string', 'max:255'],
        ];
    }
}
