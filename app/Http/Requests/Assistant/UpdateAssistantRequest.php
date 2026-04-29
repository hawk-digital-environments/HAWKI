<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'handle' => [
                'sometimes',
                'nullable',
                'string',
                'max:255',
                'unique:assistants,handle,' . $this->assistant?->id,
                'unique:ai_models,label',
            ],
            'system_prompt' => 'sometimes|nullable|string',
            'greeting' => 'sometimes|nullable|string',
            'description' => 'sometimes|nullable|string',
            'detail_description' => 'sometimes|nullable|string',
            'allow_remix' => 'sometimes|boolean',
            'allow_model_select' => 'sometimes|boolean',
            'language' => 'sometimes|string',
            'category' => 'sometimes|string',
            'review_stage' => 'sometimes|string',
            'formality' => 'sometimes|string',
            'model' => 'sometimes|string',
            'model_length' => 'sometimes|integer|min:1',
            'model_temp' => 'sometimes|numeric|min:0|max:1',
            'model_top_p' => 'sometimes|numeric|min:0|max:1',
            'user_prompts' => 'sometimes|array',
            'user_prompts.*.text' => 'required_with:user_prompts|string',
            'ai_tools' => 'sometimes|array',
            'ai_tools.*.id' => 'required_with:ai_tools|exists:ai_tools,id',
            'version_text' => 'sometimes|nullable|string',
        ];
    }
}
