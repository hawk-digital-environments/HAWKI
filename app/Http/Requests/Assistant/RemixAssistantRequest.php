<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class RemixAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('remix', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'data.relationships.organization.data.id' => 'nullable|integer',
        ];
    }

    public function organizationId(): ?int
    {
        /** @var int|null $id */
        $id = $this->validated('data.relationships.organization.data.id');

        return null !== $id ? (int) $id : null;
    }
}
