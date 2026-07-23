<?php
declare(strict_types=1);


namespace App\Services\System\Exceptions;


/**
 * Thrown when a code path that is required by a contract or interface has not been implemented yet.
 *
 * Use the static {@see forReason()} factory to provide a message explaining what is missing.
 */
class NotImplementedException extends \LogicException implements SystemExceptionInterface
{
    /**
     * Creates an exception for a specific unimplemented functionality.
     *
     * @param string $reason Short description of what has not been implemented yet.
     */
    public static function forReason(string $reason): self
    {
        return new self("Not implemented: {$reason}");
    }
}
