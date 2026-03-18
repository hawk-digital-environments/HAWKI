<?php

namespace App\Providers;

use App\Services\FileConverter\Exception\InvalidFileConverterTypeException;
use App\Services\FileConverter\Handlers\FileConverterInterface;
use App\Services\FileConverter\Handlers\NullFileConverter;
use Illuminate\Support\ServiceProvider;

class FileConverterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileConverterInterface::class, function () {
            $candidates = [
                config('file_converter.default'),
                config('file_converter.fallback'),
            ];

            foreach ($candidates as $converterType) {
                if (empty($converterType)) {
                    continue;
                }

                $converterConfig = $this->getConverterConfig($converterType);

                if ($this->isConfiguredConverterConfig($converterConfig)) {
                    $converterClass = $converterConfig['class'];
                    $instance = new $converterClass($converterConfig);
                    if ($instance->isAvailable()) {
                        return $instance;
                    }
                }
            }

            return new NullFileConverter();
        });
    }

    public function boot(): void
    {
    }

    /**
     * Fetches the converter configuration for the given type from the config file.
     * If the configuration is missing or invalid, it throws an InvalidFileConverterTypeException.
     */
    private function getConverterConfig(mixed $type): array
    {
        $converterConfig = config("file_converter.converters.$type");
        if (!is_array($converterConfig) || empty($converterConfig)) {
            throw new InvalidFileConverterTypeException($type);
        }
        return $converterConfig;
    }

    /**
     * Checks if the given converter configuration is properly set up with all required fields.
     * It checks for the presence of 'api_url', 'api_key', and 'class' fields, and also verifies that
     * the specified class exists and implements the FileConverterInterface.
     */
    private function isConfiguredConverterConfig(array $converterConfig): bool
    {
        return !empty($converterConfig['api_url'])
            && !empty($converterConfig['api_key'])
            && !empty($converterConfig['class'])
            && class_exists($converterConfig['class'])
            && in_array(FileConverterInterface::class, class_implements($converterConfig['class']), true);
    }
}
