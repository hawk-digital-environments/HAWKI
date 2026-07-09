<?php
declare(strict_types=1);


namespace App\Services\Storage\Exception;


/**
 * Marker interface for all exceptions thrown by the Storage domain.
 * Catch this interface to handle any storage-related failure without coupling to concrete exception classes.
 */
interface StorageExceptionInterface extends \Throwable
{

}
