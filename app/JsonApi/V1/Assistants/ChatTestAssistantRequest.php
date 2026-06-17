<?php

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\AI\AiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;

class ChatTestAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => ['required_without:messages'],
            'stream' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $model = $this->input('model');
                if ($model === null || $model === '') {
                    return;
                }

                $assistantId = $this->route('assistantId');
                if ($assistantId === null) {
                    return;
                }

                $assistant = Assistant::find($assistantId);
                if ($assistant === null) {
                    return;
                }

                if (! $assistant->allow_model_select && $model !== $assistant->model) {
                    $validator->errors()->add(
                        'model',
                        'The requested model is not allowed. This assistant does not allow model selection.'
                    );

                    return;
                }

                $aiModel = app(AiService::class)->getModel($model);
                if ($aiModel === null) {
                    $validator->errors()->add(
                        'model',
                        "The model '{$model}' is not available."
                    );
                }
            },
        ];
    }
}
