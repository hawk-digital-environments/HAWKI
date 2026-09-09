<?php
declare(strict_types=1);


namespace App\Services\Announcements\Exceptions;


use App\Models\Announcements\Announcement;
use RuntimeException;

/**
 * Thrown when publishing a policy whose validity window overlaps an existing one.
 *
 * At any point in time exactly one policy has to be *the* policy: consent is recorded against a
 * single announcement id, and the registration flow asks for "the current policy". Two
 * overlapping policies make that question ambiguous, and the tie-break in
 * {@see \App\Services\Announcements\Repositories\PolicyAnnouncementRepository::findCurrentPolicy()}
 * would silently pick one — users would consent to a document nobody intended to publish.
 * Better to refuse at publish time, where a human can fix the dates.
 */
class OverlappingPolicyException extends RuntimeException implements AnnouncementExceptionInterface
{
    /**
     * @param iterable<Announcement> $conflicts
     */
    public static function forConflicts(iterable $conflicts): self
    {
        $descriptions = [];
        foreach ($conflicts as $conflict) {
            $descriptions[] = sprintf(
                '#%d "%s" (%s - %s)',
                $conflict->id,
                $conflict->title,
                $conflict->starts_at?->toDateTimeString() ?? 'always',
                $conflict->expires_at?->toDateTimeString() ?? 'never'
            );
        }

        return new self(
            'The policy overlaps with already published policies: ' . implode(', ', $descriptions) .
            '. Set an expiry on the existing policy (or a later start on the new one) so that only ' .
            'one policy is in effect at a time.'
        );
    }
}
