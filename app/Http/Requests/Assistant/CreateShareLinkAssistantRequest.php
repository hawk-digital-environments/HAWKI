<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class CreateShareLinkAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('createShareLink', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['sometimes', 'array'],
            'data.can_edit' => ['sometimes', 'boolean'],
        ];
    }

    public function canEdit(): bool
    {
        return (bool) $this->validated('data.can_edit', false);
    }
}
