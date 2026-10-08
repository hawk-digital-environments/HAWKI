<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * One turn of the assistant builder's guide chat. Only someone who may edit
 * the assistant gets the guide, since its answers are meant to be written
 * into the assistant.
 */
class AssistantBuilderGuideRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'messages' => ['required', 'array', 'min:1', 'max:60'],
            'messages.*.role' => ['required', 'string', 'in:user,assistant'],
            'messages.*.content' => ['required', 'string', 'max:20000'],
            'draft' => ['present', 'array'],
            'draft.name' => ['nullable', 'string'],
            'draft.handle' => ['nullable', 'string'],
            'draft.categoryId' => ['nullable', 'string'],
            'draft.model' => ['nullable', 'string'],
            'draft.description' => ['nullable', 'string'],
            'draft.detailDescription' => ['nullable', 'string'],
            'draft.systemPrompt' => ['nullable', 'string'],
            'draft.greeting' => ['nullable', 'string'],
            'draft.starterPrompts' => ['nullable', 'array'],
            'draft.starterPrompts.*' => ['string'],
            'draft.language' => ['nullable', 'string'],
            'draft.formality' => ['nullable', 'string'],
            'draft.answerStyle' => ['nullable', 'string'],
            'draft.tags' => ['nullable', 'array'],
            'draft.tags.*' => ['string'],
            'draft.avatar' => ['nullable', 'array'],
            'draft.avatar.emoji' => ['nullable', 'string', 'max:32'],
            'draft.avatar.background' => ['nullable', 'string', 'max:64'],
            'avatarBackgrounds' => ['sometimes', 'array', 'max:50'],
            'avatarBackgrounds.*' => ['string', 'distinct', 'regex:/^[a-z0-9-]{1,64}$/'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $messages = $this->input('messages');
            if (\is_array($messages) && 'user' !== (end($messages)['role'] ?? null)) {
                $validator->errors()->add('messages', 'The last message must be from the user.');
            }
        });
    }

    /**
     * @return list<array{role: string, content: string}>
     */
    public function guideMessages(): array
    {
        return array_values(array_map(
            static fn (array $m): array => ['role' => $m['role'], 'content' => $m['content']],
            $this->validated('messages'),
        ));
    }

    /**
     * Ids of the avatar background presets the builder offers.
     *
     * @return list<string>
     */
    public function avatarBackgrounds(): array
    {
        return array_values($this->validated('avatarBackgrounds') ?? []);
    }

    /**
     * @return array<string, mixed>
     */
    public function draft(): array
    {
        return $this->validated('draft') ?? [];
    }
}
