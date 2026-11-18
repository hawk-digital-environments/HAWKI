<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Services\Assistant\Values\AssistantShareGrant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SyncSharesAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('syncShares', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['present', 'array'],
            'data.*.type' => ['required', 'string', 'in:users'],
            'data.*.id' => ['required', 'integer', 'distinct'],
            'data.*.meta.can_edit' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<AssistantShareGrant>
     */
    public function shares(): array
    {
        /** @var array<int, array{type: string, id: int|string, meta?: array{can_edit?: bool}}> $identifiers */
        $identifiers = $this->validated('data', []);

        return array_values(array_map(AssistantShareGrant::fromArray(...), $identifiers));
    }
}
