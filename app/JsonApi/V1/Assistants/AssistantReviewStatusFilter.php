<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Assistants;

use App\Services\Assistant\Values\AssistantReviewStatus;
use Illuminate\Support\Facades\Auth;
use LaravelJsonApi\Eloquent\Contracts\Filter;

/**
 * Filters to the current user's own assistants by review status (comma-
 * separated). Creator-scoped on purpose: review states are organization
 * business, and the org-wide queue is the admin surface (assistant-reviews).
 */
class AssistantReviewStatusFilter implements Filter
{
    public static function make(): self
    {
        return new self();
    }

    public function key(): string
    {
        return 'review_status';
    }

    public function apply($query, $value)
    {
        $statuses = array_values(array_filter(
            array_map('trim', explode(',', (string) $value)),
            static fn (string $status): bool => null !== AssistantReviewStatus::tryFrom($status),
        ));

        if ([] === $statuses) {
            return $query;
        }

        $user = Auth::user();

        if (null === $user) {
            return $query;
        }

        return $query
            ->where('assistants.creator_id', $user->id)
            ->whereHas('assistantReview', static fn ($review) => $review->whereIn('status', $statuses));
    }

    public function isSingular(): bool
    {
        return false;
    }
}
