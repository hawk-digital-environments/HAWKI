<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Thrown when a share link token is unknown or expired. Rendered as 403.
 */
final class InvalidShareLinkException extends AccessDeniedHttpException implements AssistantExceptionInterface
{
    public static function make(): self
    {
        return new self('This share link is invalid or has expired.');
    }
}
