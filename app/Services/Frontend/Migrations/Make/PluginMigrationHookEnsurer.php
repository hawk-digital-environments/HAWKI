<?php

declare(strict_types=1);

namespace App\Services\Frontend\Migrations\Make;

use App\Services\Frontend\Migrations\Make\Values\EnsuredPluginMigrationHook;
use App\Services\Frontend\Plugin\PluginFs;
use Illuminate\Filesystem\Filesystem;

readonly class PluginMigrationHookEnsurer
{
    public function __construct(
        private Filesystem $files,
        private PluginFs $pluginFs,
    ) {
    }

    /**
     * Ensures the plugin's `.plugin.ts` file has a `migrations()` hook that globs the correct
     * migrations directory. Adds or updates the hook, the `MigrationRegistrar` import, and the
     * `HawkiCorePlugin` type as needed.
     */
    public function ensure(string $plugin): EnsuredPluginMigrationHook
    {
        $pluginFile = $this->pluginFs->pluginFilePath($plugin);
        $content = $this->files->get($pluginFile);
        $expectedGlob = $this->pluginFs->migrationsTsGlob($plugin);

        if (str_contains($content, $expectedGlob)) {
            return new EnsuredPluginMigrationHook($pluginFile, EnsuredPluginMigrationHook::STATUS_ALREADY_CONFIGURED);
        }

        if (preg_match('/\bmigrations\s*\(/', $content) === 1) {
            $this->files->put($pluginFile, $this->updateGlob($content, $expectedGlob));

            return new EnsuredPluginMigrationHook($pluginFile, EnsuredPluginMigrationHook::STATUS_UPDATED_GLOB);
        }

        $this->files->put($pluginFile, $this->addMigrationHook($content, $expectedGlob));

        return new EnsuredPluginMigrationHook($pluginFile, EnsuredPluginMigrationHook::STATUS_ADDED_HOOK);
    }

        $offset = 0;

        if (preg_match('/\bmigrations\s*\(/', $content, $migrationMatch, \PREG_OFFSET_CAPTURE) === 1) {
            $offset = $migrationMatch[0][1];
        }

        if (preg_match('/import\.meta\.glob\(\s*[\'\"][^\'\"]*[\'\"]/', $content, $matches, \PREG_OFFSET_CAPTURE, $offset) === 1) {
            return substr_replace($content, "import.meta.glob('" . $glob . "'", $matches[0][1], \strlen($matches[0][0]));
        }

        return $content;
    }

    private function addMigrationHook(string $content, string $glob): string
    {
        $content = $this->ensureImport($content, "import type {MigrationRegistrar} from '\$lib/kernel/migrations/migrationRegistrar.js';");
        $content = $this->ensureCorePluginType($content);

        $method = "    public migrations(registrar: MigrationRegistrar): void | Promise<void> {\n"
            . "        registrar.addFromModules(import.meta.glob('" . $glob . "', {eager: false}));\n"
            . "    }\n";

        $lastBrace = mb_strrpos($content, '}');

        if (false === $lastBrace) {
            return $content . "\n" . $method;
        }

        return mb_substr($content, 0, $lastBrace) . $method . mb_substr($content, $lastBrace);
    }

    private function ensureImport(string $content, string $import): string
    {
        if (str_contains($content, $import)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $lastImportIndex = -1;

        foreach ($lines as $index => $line) {
            if (preg_match('/^import\b/', mb_ltrim($line)) === 1) {
                $lastImportIndex = $index;
            }
        }

        if (0 <= $lastImportIndex) {
            array_splice($lines, $lastImportIndex + 1, 0, [$import]);
        } else {
            array_unshift($lines, $import);
        }

        return implode("\n", $lines);
    }

    private function ensureCorePluginType(string $content): string
    {
        if (!str_contains($content, 'HawkiCorePlugin')) {
            if (str_contains($content, 'HawkiPlugin')) {
                $content = preg_replace('/\{HawkiPlugin\}/', '{HawkiCorePlugin}', $content, 1);
            } else {
                $content = $this->ensureImport($content, "import type {HawkiCorePlugin} from '\$lib/kernel/plugins/types.js';");
            }
        }

        if (str_contains($content, 'implements HawkiPlugin')) {
            $content = str_replace('implements HawkiPlugin', 'implements HawkiCorePlugin', $content);
        }

        return $content;
    }
}
