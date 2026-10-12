<?php

declare(strict_types=1);

namespace App\Console\Commands\Make;

use App\Services\System\Database\SettingsAndConfig\ConfigAndSettingsBlueprint;
use App\Services\System\Database\SettingsAndConfig\ConfigSchema;

class MakeConfigSchemaMigrationCommand extends AbstractMakeSchemaMigrationCommand
{
    protected $signature = 'make:config-schema-migration {class : The config class the migration operates on}';
    protected $description = 'Create a new migration managing app-config rows via ConfigSchema';

    protected static function schemaFqn(): string
    {
        return ConfigSchema::class;
    }

    protected static function blueprintFqn(): string
    {
        return ConfigAndSettingsBlueprint::class;
    }

    protected static function migrationNameDomain(): string
    {
        return 'config';
    }

    protected function classQuestion(): string
    {
        return 'Which config class does the migration operate on?';
    }
}
