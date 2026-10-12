<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Users\Settings\CoreUserSettings;
use App\Services\Users\Settings\Registries\UserSettingsRegistry;
use Illuminate\Support\ServiceProvider;

class UsersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->extend(
            UserSettingsRegistry::class,
            static function (UserSettingsRegistry $registry) {
                return $registry->declare(CoreUserSettings::class);
            },
        );
    }
}
