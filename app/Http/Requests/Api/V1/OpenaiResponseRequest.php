<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1;

use App\Models\Assistants\Assistant;
use App\Services\Ai\AiService;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a generic, stateless chat exchange submitted to
 * {@see \App\Http\Controllers\Api\V1\OpenaiResponsesController}.
 *
 * The request is assistant-aware but not assistant-scoped: callers may pass an
 * optional {@see $assistant_handle} to build the exchange from an assistant
 * (system prompt, tools, parameters, model), or omit it for a bare model run.
 */
class OpenaiResponseRequest extends FormRequest
{
    private ?Assistant $assistant = null;
    private bool $assistantLoaded = false;

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
            'assistant_handle' => ['nullable', 'string', 'exists:assistants,handle'],
        ];
    }

    public function messages(): array
    {
        return [
            'assistant_handle.exists' => "No assistant found for handle ':input'.",
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

                $assistant = $this->assistant();

                if ($assistant !== null
                    && !$assistant->allow_model_select
                    && $modelId !== $assistant->model
                ) {
                    $validator->errors()->add(
                        'model',
                        'The requested model is not allowed. This assistant does not allow model selection.'
                    );

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

    /**
     * Resolve and cache the assistant referenced by {@see $assistant_handle}.
     *
     * Existence is already enforced declaratively via the {@see Rule::exists}
     * validation rule, so this only performs a lookup when a handle was
     * submitted. The result is cached so the controller and the {@see after()}
     * validator share a single query.
     */
    public function assistant(): ?Assistant
    {
        if ($this->assistantLoaded) {
            return $this->assistant;
        }

        $this->assistantLoaded = true;

        $handle = $this->input('assistant_handle');

        if ($handle === null || $handle === '') {
            return null;
        }

        return $this->assistant = Assistant::where('handle', $handle)->first();
    }
}
