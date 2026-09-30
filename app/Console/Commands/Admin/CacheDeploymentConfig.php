<?php
declare(strict_types=1);

namespace App\Console\Commands\Admin;

use Illuminate\Foundation\Console\ConfigCacheCommand;

/** Keep runtime database overrides out of Laravel's deployment cache. */
class CacheDeploymentConfig extends ConfigCacheCommand
{
    private static bool $building = false;

    public static function isBuilding(): bool
    {
        return self::$building;
    }

    protected function getFreshConfiguration(): array
    {
        self::$building = true;
        try {
            return parent::getFreshConfiguration();
        } finally {
            self::$building = false;
        }
    }
}
