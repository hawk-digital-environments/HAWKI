<?php

declare(strict_types=1);

namespace App\Services\Assistant\Exceptions;

/**
 * Marker interface for all exceptions thrown by the Assistant domain. Allows
 * callers to catch any Assistant-domain failure with a single
 * `catch (AssistantExceptionInterface $e)` clause.
 */
interface AssistantExceptionInterface extends \Throwable
{
}
