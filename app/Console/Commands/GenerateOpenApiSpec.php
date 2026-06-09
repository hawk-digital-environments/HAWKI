<?php

namespace App\Console\Commands;

use App\Services\OpenApi\OpenApiGenerator;
use Illuminate\Console\Command;

class GenerateOpenApiSpec extends Command
{
    protected $signature = 'openapi:generate
        {--output= : Output file path}
        {--no-examples : Disable database query examples for filter parameters}
        {--docs-dir= : Output directory (default: public/docs)}';

    protected $description = 'Generate OpenAPI specification from JSON:API schemas and routes';

    public function handle(OpenApiGenerator $generator): int
    {
        $withExamples = ! $this->option('no-examples');

        $this->info('Generating OpenAPI specification...');

        try {
            $spec = $generator->generate($withExamples);

            $docsDir = $this->option('docs-dir')
                ? base_path($this->option('docs-dir'))
                : public_path('docs');

            if (! is_dir($docsDir)) {
                mkdir($docsDir, 0755, true);
            }

            $jsonPath = $docsDir.'/openapi.json';
            file_put_contents($jsonPath, $generator->toJson($spec));

            $outputPath = $this->option('output') ?? $jsonPath;
            if ($outputPath !== $jsonPath) {
                file_put_contents($outputPath, $generator->toJson($spec));
            }

            $pathCount = count($spec['paths'] ?? []);
            $schemaCount = count($spec['components']['schemas'] ?? []);
            $this->info("Generated {$pathCount} paths with {$schemaCount} component schemas.");
            $this->info("Output: {$jsonPath}");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error("Generation failed: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");

            return self::FAILURE;
        }
    }
}
