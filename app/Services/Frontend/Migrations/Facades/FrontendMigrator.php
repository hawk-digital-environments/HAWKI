<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Facades;


use App\Services\Frontend\Migrations\FrontendMigrationBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * Facade for `FrontendMigrationBuilder`, intended for use inside Laravel migration files.
 *
 * @method static void register(string $migrationName, \Closure|null $userDataFinder = null)
 *
 * @see FrontendMigrationBuilder
 */
class FrontendMigrator extends Facade
{
    /**
     * @inheritDoc
     */
    protected static function getFacadeAccessor(): string
    {
        return FrontendMigrationBuilder::class;
    }
}
