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
            'data.shares' => ['present', 'array'],
            'data.shares.*.user_id' => ['required', 'integer', 'distinct'],
            'data.shares.*.can_edit' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<AssistantShareGrant>
     */
    public function shares(): array
    {
        /** @var array<int, array{user_id: int, can_edit?: bool}> $shares */
        $shares = $this->validated('data.shares', []);

        return array_values(array_map(AssistantShareGrant::fromArray(...), $shares));
    }
}
