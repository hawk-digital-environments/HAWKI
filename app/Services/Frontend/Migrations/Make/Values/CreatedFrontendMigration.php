<?php

declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Make\Values;

/**
 * Result of {@see \App\Services\Frontend\Migrations\Make\FrontendMigrationCreator::create()} —
 * the absolute paths of the scaffolded backend and JS migration files plus the outcome of
 * ensuring the plugin's `migrations()` hook.
 */
readonly class CreatedFrontendMigration
{
    public function __construct(
        /**
         * Absolute path to the generated Laravel database migration file.
         */
        public string $backendPath,
        /**
         * Absolute path to the generated TypeScript migration file.
         */
        public string $jsPath,
        /**
         * Absolute path to the plugin's `.plugin.ts` file that was inspected/updated.
         */
        public string $pluginPath,
        /**
         * Outcome of the plugin hook check — one of the {@see EnsuredPluginMigrationHook} status constants.
         */
        public string $pluginStatus,
    ) {
    }
}
