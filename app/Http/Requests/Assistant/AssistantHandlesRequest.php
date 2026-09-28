<?php

declare(strict_types=1);

namespace App\Http\Requests\Assistant;

use App\Services\Assistant\Values\AssistantFieldRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The assistants `actions/handles` query: which of the given handles
 * already exist. Existence is global (handles are unique across all
 * assistants regardless of visibility); the answer discloses nothing
 * beyond existence. An absent or empty filter answers with an empty list,
 * duplicate handles are ignored.
 */
class AssistantHandlesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Existence probing is for signed-in members — the same disclosure
        // a rejected create makes, but not for anonymous traffic.
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'handles' => [
                'nullable',
                'string',
                // The same charset and length the assistant attributes
                // themselves enforce (see AssistantFieldRules).
                static function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (self::split($value) as $handle) {
                        if (mb_strlen($handle) > AssistantFieldRules::HANDLE_MAX_LENGTH
                            || !preg_match(AssistantFieldRules::HANDLE_PATTERN, $handle)
                        ) {
                            $fail("The handle '{$handle}' is not a valid handle.");

                            return;
                        }
                    }
                },
            ],
        ];
    }

    /**
     * The asked-about handles, in query order.
     *
     * @return list<string>
     */
    public function handles(): array
    {
        return self::split($this->validated('handles'));
    }

    /**
     * Splits the comma-separated filter: empty segments are dropped,
     * duplicates are ignored.
     *
     * @return list<string>
     */
    private static function split(mixed $value): array
    {
        if (!\is_string($value)) {
            return [];
        }

        $handles = array_filter(explode(',', $value), static fn (string $handle): bool => $handle !== '');

        return array_values(array_unique($handles));
    }
}
