<?php

declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Make\Values;

/**
 * Result of {@see \App\Services\Frontend\Migrations\Make\PluginMigrationHookEnsurer::ensure()} —
 * the plugin file that was inspected and whether its `migrations()` hook was already present,
 * updated, or added.
 */
readonly class EnsuredPluginMigrationHook
{
    /**
     * The plugin file already contained a `migrations()` hook globbing the correct path.
     */
    public const string STATUS_ALREADY_CONFIGURED = 'already configured';

    /**
     * A `migrations()` hook existed but globbed the wrong path; the glob was updated.
     */
    public const string STATUS_UPDATED_GLOB = 'updated migrations glob';

    /**
     * No `migrations()` hook was present; the hook, imports, and `HawkiCorePlugin` were added.
     */
    public const string STATUS_ADDED_HOOK = 'added migrations hook';

    public function __construct(
        /**
         * Absolute path to the plugin's `.plugin.ts` file.
         */
        public string $path,
        /**
         * Outcome of the check — one of the `STATUS_*` constants.
         */
        public string $status,
    ) {
    }
}
