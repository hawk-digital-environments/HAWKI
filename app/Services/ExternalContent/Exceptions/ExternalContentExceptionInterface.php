<?php
declare(strict_types=1);

namespace App\Services\ExternalContent\Exceptions;

/**
 * Marker interface for all exceptions thrown within the ExternalContent domain.
 *
 * Catch this interface to handle any error originating from external content fetching
 * regardless of the specific exception class.
 */
interface ExternalContentExceptionInterface extends \Throwable
{
}
