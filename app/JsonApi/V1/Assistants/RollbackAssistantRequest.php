<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Models\Assistants\AssistantVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

/**
 * The `actions/rollback` payload: which published version to restore.
 * Deleting every version above the target is part of the deal.
 */
class RollbackAssistantRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('rollback', $this->route('assistant'));

        return true;
    }

    public function rules(): array
    {
        /** @var null|\App\Models\Assistants\Assistant $assistant */
        $assistant = $this->route('assistant');

        return [
            'data.attributes.version' => [
                'required',
                'numeric',
                'gt:0',
                static function (string $attribute, mixed $value, \Closure $fail) use ($assistant): void {
                    if (null === $assistant) {
                        return;
                    }

                    $exists = AssistantVersion::query()
                        ->where('assistant_id', $assistant->id)
                        ->where('version', (float) $value)
                        ->exists();

                    if (!$exists) {
                        $fail("Version {$value} does not exist for this assistant.");
                    }
                },
            ],
        ];
    }

    /**
     * The published version number to restore (e.g. 1.0, 2.0).
     */
    public function version(): float
    {
        /** @var float $value */
        $value = $this->validated('data.attributes.version');

        return (float) $value;
    }
}
