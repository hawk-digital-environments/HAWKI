<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Thrown when an operation requires a single organization for a user but the
 * user belongs to more than one. Callers should re-prompt the user (or the
 * API client) to explicitly select an organization.
 */
final class AmbiguousOrganizationException extends RuntimeException implements AssistantExceptionInterface
{
    public static function forUser(User $user): self
    {
        return new self(
            sprintf(
                'User %d belongs to more than one organization; the target organization must be specified explicitly.',
                $user->id,
            ),
        );
    }
}
