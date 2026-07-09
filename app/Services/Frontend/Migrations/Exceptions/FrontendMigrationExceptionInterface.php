<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Exceptions;


/**
 * Marker interface for all exceptions thrown by the Frontend Migration subsystem.
 * Catch this type to handle any frontend-migration error in one place.
 */
interface FrontendMigrationExceptionInterface extends \Throwable
{

}
