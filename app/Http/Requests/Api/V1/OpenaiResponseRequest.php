<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Services\Ai\AiService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a generic, stateless chat exchange submitted to
 * {@see \App\Http\Controllers\Api\V1\OpenaiResponsesController}.
 *
 * Callers may pass a model id explicitly, or omit it for a bare model run
 * that falls back to the system default chat model.
 */
class OpenaiResponseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'input' => ['required_without:messages'],
            'messages' => ['nullable', 'array'],
            'stream' => ['nullable', 'boolean'],
            'model' => ['nullable', 'string'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $modelId = $this->input('model');

                if ($modelId === null || $modelId === '') {
                    return;
                }

                if (app(AiService::class)->getModels()->findOne($modelId) === null) {
                    $validator->errors()->add(
                        'model',
                        "The model '{$modelId}' is not available."
                    );
                }
            },
        ];
    }
}
