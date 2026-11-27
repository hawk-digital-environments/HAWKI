<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class SyncSharesAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('manageShares', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        return [
            'data' => ['present', 'array'],
            'data.*.type' => ['required', 'string', 'in:users'],
            'data.*.id' => ['required', 'integer', 'distinct'],
        ];
    }

    /**
     * @return list<int>
     */
    public function userIds(): array
    {
        /** @var array<int, array{type: string, id: int|string}> $identifiers */
        $identifiers = $this->validated('data', []);

        return array_values(array_map(static fn (array $identifier): int => (int) $identifier['id'], $identifiers));
    }
}
