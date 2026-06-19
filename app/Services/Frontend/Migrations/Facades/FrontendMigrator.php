<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Facades;


use App\Services\Frontend\Migrations\FrontendMigrationBuilder;
use Illuminate\Support\Facades\Facade;

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
