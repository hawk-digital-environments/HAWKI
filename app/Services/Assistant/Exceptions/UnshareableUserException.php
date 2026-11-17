<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a share sync names a user that cannot be a share target:
 * unknown, removed, a system/app account, or the creator themselves
 * (who already has full access). Rendered as 422.
 */
final class UnshareableUserException extends UnprocessableEntityHttpException implements AssistantExceptionInterface
{
    /**
     * @param list<int> $userIds
     */
    public static function forIds(array $userIds): self
    {
        return new self('These users cannot be shared with: ' . implode(', ', $userIds));
    }
}
