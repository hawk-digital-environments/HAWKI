<?php

declare(strict_types=1);

namespace App\Services\Frontend\Plugin;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Single source of truth for the on-disk layout of frontend plugins and the matching
 * TypeScript path/glob strings emitted into generated code.
 *
 * Frontend plugins live under `resources/js/plugins/`, which the Vite `$lib` alias resolves
 * to `$lib/plugins/`. Each plugin is a subdirectory containing a `*.plugin.ts` file, discovered
 * by `PluginExtension` via a recursive glob (see {@see pluginFileTsGlob()}).
 *
 * Centralising these strings here keeps the backend (artisan commands, migration scaffolders)
 * and the TypeScript it generates in sync with the frontend's plugin discovery contract.
 */
readonly class PluginFs
{
    /**
     * TypeScript prefix for the plugins root, matching the Vite `$lib` alias.
     */
    public const string TS_PLUGINS_PREFIX = '$lib/plugins';

    /**
     * Glob pattern used by `PluginExtension` to discover `*.plugin.ts` files.
     */
    public const string PLUGIN_FILE_TS_GLOB = self::TS_PLUGINS_PREFIX . '/**/*.plugin.ts';

    public function __construct(private Filesystem $files)
    {
    }

    /**
     * Absolute filesystem path to the plugins root directory.
     */
    public function pluginsRoot(): string
    {
        return resource_path('js/plugins');
    }

    /**
     * TypeScript glob matching every `*.plugin.ts` file across all plugins (the discovery glob).
     */
    public function pluginFileTsGlob(): string
    {
        return self::PLUGIN_FILE_TS_GLOB;
    }

    /**
     * Lists every plugin name — a directory under the plugins root that contains a `*.plugin.ts`.
     *
     * @return array<int, string>
     */
    public function pluginNames(): array
    {
        $names = [];

        foreach ($this->files->directories($this->pluginsRoot()) as $directory) {
            $name = basename($directory);

            if ($this->hasPluginFile($directory)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /**
     * Resolves the absolute filesystem path of a plugin's `*.plugin.ts` file.
     *
     * @throws \RuntimeException When the plugin has no (or multiple) `*.plugin.ts` file.
     */
    public function pluginFilePath(string $plugin): string
    {
        $matches = $this->files->glob($this->pluginDirectory($plugin) . '/*.plugin.ts');

        if (\count($matches) === 0) {
            throw new \RuntimeException("No .plugin.ts file found for plugin '{$plugin}'.");
        }

        if (\count($matches) > 1) {
            throw new \RuntimeException("Plugin '{$plugin}' has multiple .plugin.ts files.");
        }

        return $matches[0];
    }

    /**
     * Absolute filesystem path to a plugin's migrations directory.
     */
    public function migrationsDirectory(string $plugin): string
    {
        return $this->pluginDirectory($plugin) . '/migrations';
    }

    /**
     * Absolute filesystem path to a plugin's migrations directory for the given run type.
     * The run type is snake-cased to match the directory convention used by the frontend runner.
     */
    public function migrationsDirectoryForRunType(string $plugin, string $runType): string
    {
        return $this->migrationsDirectory($plugin) . '/' . Str::snake($runType);
    }

    /**
     * TypeScript `import.meta.glob` pattern matching every `*.ts` file under a plugin's
     * migrations directory — used by the plugin's `migrations()` lifecycle hook.
     */
    public function migrationsTsGlob(string $plugin): string
    {
        return self::TS_PLUGINS_PREFIX . '/' . $plugin . '/migrations/**/*.ts';
    }

    /**
     * Absolute filesystem path to a plugin's root directory.
     */
    private function pluginDirectory(string $plugin): string
    {
        return $this->pluginsRoot() . '/' . $plugin;
    }

    /**
     * Returns true when `$directory` contains at least one `*.plugin.ts` file.
     */
    private function hasPluginFile(string $directory): bool
    {
        return !empty($this->files->glob($directory . '/*.plugin.ts'));
    }
}
