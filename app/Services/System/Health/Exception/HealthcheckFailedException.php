<?php
declare(strict_types=1);


namespace App\Services\System\Health\Exception;


/**
 * Thrown inside {@see \App\Services\System\Health\HealthChecker} when a health check
 * detects a functional failure — as opposed to an infrastructure exception thrown by the
 * underlying driver (DB, Redis, etc.).
 *
 * Examples of functional failures: a cache write succeeds but the subsequent read returns
 * a different value, or the storage directory exists but is not writable.
 */
class HealthcheckFailedException extends \RuntimeException
{
}
