<?php

declare(strict_types=1);

namespace App\Console\Commands\Make;

use App\Services\System\Database\SettingsAndConfig\UserSettingsBlueprint;
use App\Services\System\Database\SettingsAndConfig\UserSettingsSchema;

class MakeUserSettingsMigrationCommand extends AbstractMakeSchemaMigrationCommand
{
    protected $signature = 'make:user-settings-migration {class : The user-settings class the migration operates on}';
    protected $description = 'Create a new migration managing user-settings rows via UserSettingsSchema';

    protected static function schemaFqn(): string
    {
        return UserSettingsSchema::class;
    }

    protected static function blueprintFqn(): string
    {
        return UserSettingsBlueprint::class;
    }

    protected static function migrationNameDomain(): string
    {
        return 'user_settings';
    }

    protected function classQuestion(): string
    {
        return 'Which user-settings class does the migration operate on?';
    }
}
