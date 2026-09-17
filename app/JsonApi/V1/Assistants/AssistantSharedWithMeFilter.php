<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Support\Facades\Auth;
use LaravelJsonApi\Eloquent\Contracts\Filter;
use LaravelJsonApi\Eloquent\Filters\Concerns\DeserializesValue;
use LaravelJsonApi\Eloquent\Filters\Concerns\IsSingular;

/**
 * Filters down to assistants shared with the current user via the
 * `sharedUsers` pivot, independent of their release stage.
 */
class AssistantSharedWithMeFilter implements Filter
{
    use DeserializesValue;
    use IsSingular;

    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'shared_with_me';
    }

    public function apply($query, $value)
    {
        if (! filter_var($value, \FILTER_VALIDATE_BOOLEAN)) {
            return $query;
        }

        $user = Auth::user();

        if (null === $user) {
            return $query;
        }

        return app(AssistantRepository::class)->filterSharedWithUser($query, $user);
    }
}
