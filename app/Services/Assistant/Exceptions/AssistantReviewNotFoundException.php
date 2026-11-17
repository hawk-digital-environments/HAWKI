<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Thrown when a decision targets a review row that vanished between the
 * request's fetch and the decision's row lock. Extends the 422 HTTP
 * exception so the JSON:API parser keeps rendering it as a validation
 * failure — the request validator is the primary gate, this is the
 * race-condition backstop.
 */
final class AssistantReviewNotFoundException extends UnprocessableEntityHttpException implements AssistantExceptionInterface
{
    public static function duringDecision(): self
    {
        return new self('The requested review does not exist.');
    }
}
