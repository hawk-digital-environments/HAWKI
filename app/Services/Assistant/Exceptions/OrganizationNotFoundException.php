<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use App\Models\User;
use RuntimeException;

/**
 * Thrown when an operation explicitly requested an organization the user is
 * not a member of.
 */
final class OrganizationNotFoundException extends RuntimeException implements AssistantExceptionInterface
{
    public static function forUserAndId(User $user, int $organizationId): self
    {
        return new self(
            sprintf(
                'User %d is not a member of organization %d.',
                $user->id,
                $organizationId,
            ),
        );
    }
}
