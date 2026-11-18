<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use Illuminate\Support\Facades\Auth;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Narrows a collection to assistants the current user created. The "my
 * drafts"/"my private" pages need it: plain visibility already includes
 * assistants other users shared with you, which must not mix into the
 * creator's own listings.
 */
class AssistantCreatorFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'is_creator';
    }

    public function apply($query, $value)
    {
        if (! filter_var($value, \FILTER_VALIDATE_BOOLEAN)) {
            return $query;
        }

        return $query->where('creator_id', Auth::id());
    }
}
