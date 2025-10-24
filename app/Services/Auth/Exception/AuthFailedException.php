<?php
declare(strict_types=1);


namespace App\Services\Auth\Exception;

/**
 * Exception thrown when authentication fails.
 * The code is the HTTP status code that should be returned to the user.
 * The message will be visible to the user, so it should be user-friendly and not expose sensitive information!
 * If a previous exception is provided, it will be logged but not shown to the user.
 */
class AuthFailedException extends \RuntimeException
{
}
