<?php
declare(strict_types=1);


namespace App\Services\Frontend\Migrations\Make;


use Illuminate\Filesystem\Filesystem;
use RuntimeException;

readonly class PluginMigrationHookEnsurer
{
    public const string STATUS_ALREADY_CONFIGURED = 'already configured';
    public const string STATUS_ADDED_HOOK = 'added migrations hook';
    public const string STATUS_UPDATED_GLOB = 'updated migrations glob';

    public function __construct(private Filesystem $files)
    {
    }

    /**
     * @param string $plugin Plugin name (directory name under resources/js/plugins/).
     * @return array{path: string, status: string}
     */
    public function ensure(string $plugin): array
    {
        $pluginFiles = $this->files->glob(resource_path('js/plugins/' . $plugin . '/*.plugin.ts'));
        if (empty($pluginFiles)) {
            throw new RuntimeException("No .plugin.ts file found for plugin '$plugin'.");
        }
        $pluginFile = $pluginFiles[0];

        $content = $this->files->get($pluginFile);
        $expectedGlob = '$lib/plugins/' . $plugin . '/migrations/**/*.ts';

        if (str_contains($content, $expectedGlob)) {
            return ['path' => $pluginFile, 'status' => self::STATUS_ALREADY_CONFIGURED];
        }

        if (preg_match('/\bmigrations\s*\(/', $content) === 1) {
            $content = $this->updateGlob($content, $plugin);
            $this->files->put($pluginFile, $content);

            return ['path' => $pluginFile, 'status' => self::STATUS_UPDATED_GLOB];
        }

        $content = $this->addMigrationHook($content, $plugin);
        $this->files->put($pluginFile, $content);

        return ['path' => $pluginFile, 'status' => self::STATUS_ADDED_HOOK];
    }

    private function updateGlob(string $content, string $plugin): string
    {
        $glob = '$lib/plugins/' . $plugin . '/migrations/**/*.ts';
        $offset = strpos($content, 'migrations(');

        if (preg_match('/import\.meta\.glob\(\s*[\'"][^\'"]*[\'"]/', $content, $matches, PREG_OFFSET_CAPTURE, $offset) === 1) {
            return substr_replace($content, "import.meta.glob('" . $glob . "'", $matches[0][1], strlen($matches[0][0]));
        }

        return $content;
    }

    private function addMigrationHook(string $content, string $plugin): string
    {
        $content = $this->ensureImport($content, "import type {MigrationRegistrar} from '\$lib/kernel/migrations/migrationRegistrar.js';");
        $content = $this->ensureCorePluginType($content);

        $glob = '$lib/plugins/' . $plugin . '/migrations/**/*.ts';
        $method = "    public migrations(registrar: MigrationRegistrar): void | Promise<void> {\n"
            . "        registrar.addFromModules(import.meta.glob('" . $glob . "', {eager: false}));\n"
            . "    }\n";

        $lastBrace = strrpos($content, '}');
        if ($lastBrace === false) {
            return $content . "\n" . $method;
        }

        return substr($content, 0, $lastBrace) . $method . substr($content, $lastBrace);
    }

    private function ensureImport(string $content, string $import): string
    {
        if (str_contains($content, $import)) {
            return $content;
        }

        $lines = explode("\n", $content);
        $lastImportIndex = -1;
        foreach ($lines as $index => $line) {
            if (preg_match('/^import\b/', ltrim($line)) === 1) {
                $lastImportIndex = $index;
            }
        }

        if ($lastImportIndex >= 0) {
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