<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a rollback targets a version the assistant does not have, or
 * one whose frozen state was never stored. Extends the 422 HTTP exception so
 * the JSON:API parser keeps rendering it as a validation failure — the
 * request validator is the primary gate, this is the race-condition backstop.
 */
final class AssistantVersionNotFoundException extends UnprocessableEntityHttpException implements AssistantExceptionInterface
{
    public static function forVersion(float $version): self
    {
        return new self(\sprintf(
            'Version %.1f does not exist for this assistant.',
            $version,
        ));
    }

    public static function withoutPayload(float $version): self
    {
        return new self(\sprintf(
            'Version %.1f has no stored state and cannot be restored.',
            $version,
        ));
    }
}
