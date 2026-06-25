<?php

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Foundation\Http\FormRequest;

class UserPromptsAssistantRequest extends FormRequest
{
    public function __construct(
        private ?AssistantRepository $repository = null,
    ) {
        parent::__construct();
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'data.attributes.add' => ['nullable', 'array'],
            'data.attributes.add.*' => ['string'],
            'data.attributes.remove' => ['nullable', 'array'],
            'data.attributes.remove.*' => ['string'],
        ];
    }

    public function after(): array
    {
        return [
            function ($validator): void {
                $remove = $this->input('data.attributes.remove', []);

                if (! is_array($remove) || empty($remove)) {
                    return;
                }

                $assistant = $this->route('assistant');

                if (! $assistant instanceof Assistant) {
                    return;
                }

                $repository = $this->repository ?? app()->make(AssistantRepository::class);
                $existing = $repository->getUserPromptTexts($assistant);

                foreach ($remove as $index => $text) {
                    if (! in_array($text, $existing, true)) {
                        $validator->errors()->add(
                            "data.attributes.remove.{$index}",
                            "The prompt '{$text}' does not exist for this assistant."
                        );
                    }
                }
            },
        ];
    }
}
