<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus\Concerns;

/**
 * Loads corpus fixture files and turns them into PHPUnit data providers.
 *
 * Fixture directories are scanned per test class; every `*.json` becomes one case
 * named after its file, so failures point at the fixture under test.
 */
trait LoadsCorpusFixtures
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function corpusProvider(string $relativeDir): array
    {
        $dir = __DIR__ . '/../CorpusFixtures/' . $relativeDir;

        self::assertDirectoryExists($dir);

        $cases = [];

        foreach (glob($dir . '/*.json') ?: [] as $path) {
            $cases[basename($path, '.json')] = [$path];
        }

        self::assertNotEmpty($cases, "No fixtures found in {$dir}.");

        return $cases;
    }

    /**
     * @return array<string, mixed>
     */
    public static function loadFixture(string $path): array
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        self::assertIsArray($decoded, "Fixture {$path} is not a JSON object.");

        return $decoded;
    }
}
