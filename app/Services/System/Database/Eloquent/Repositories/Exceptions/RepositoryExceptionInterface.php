<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories\Exceptions;

/**
 * Marker interface for all exceptions originating from the Eloquent repository layer.
 * Catch this interface when you want to handle any repository exception without coupling
 * to a specific exception class.
 */
interface RepositoryExceptionInterface extends \Throwable
{
}
