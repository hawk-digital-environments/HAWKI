<?php

namespace App\Providers;

use App\Services\FileConverter\Exception\InvalidFileConverterConfigException;
use App\Services\FileConverter\Handlers\NullFileConverter;
use App\Services\FileConverter\Interfaces\FileConverterInterface;
use App\Services\FileConverter\Utils\ImagePreProcessingConverter;
use Illuminate\Cache\Repository;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class FileConverterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(FileConverterInterface::class, function () {
            $config = $this->app->get('config');
            $candidates = [
                $config->get('file_converter.default'),
                $config->get('file_converter.fallback'),
            ];

            foreach ($candidates as $converterType) {
                if (empty($converterType)) {
                    continue;
                }

                $converterConfig = $this->getConverterConfig($converterType);
                /** @var class-string<FileConverterInterface> $converterClass */
                $converterClass = $this->getConverterClassName($converterType, $converterConfig);

                if (!$converterClass::isValidConfig($converterConfig)) {
                    continue;
                }

                /** @var FileConverterInterface $instance */
                $instance = $this->app->make($converterClass, ['config' => $converterConfig]);

                if ($instance->isAvailable()) {
                    $binaries = $config->get('file_converter.binaries', []);

                    // Wrap it with the image pre-processing converter
                    $wrappedInstance = new ImagePreProcessingConverter(
                        concreteConverter: $instance,
                        logger: $this->app->make(LoggerInterface::class),
                        cache: $this->app->make(Repository::class),
                        rsvgConvertBinary: $binaries['rsvg_convert'] ?? 'rsvg-convert',
                        imageMagickBinary: $binaries['image_magick'] ?? 'convert',
                    );

                    $wrappedInstance->setConfig($converterConfig);

                    return $wrappedInstance;
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
     * If the configuration is missing or invalid, it throws an InvalidFileConverterConfigException.
     */
    private function getConverterConfig(mixed $type): array
    {
        $converterConfig = config("file_converter.converters.$type");
        if (!is_array($converterConfig) || empty($converterConfig)) {
            throw InvalidFileConverterConfigException::forInvalidConverterType($type);
        }
        return $converterConfig;
    }

    /**
     * Validates the converter configuration for the given type and retrieves the converter class name.
     * @param string $type The type of the converter (e.g., 'gwdg_docling').
     * @param array $config The converter configuration array containing 'class' and other necessary fields.
     * @return string
     */
    private function getConverterClassName(string $type, array $config): string
    {
        if (empty($config['class'])) {
            throw InvalidFileConverterConfigException::forMissingClassInConfig($type);
        }

        $class = $config['class'];
        if (!class_exists($class) || !in_array(FileConverterInterface::class, class_implements($class), true)) {
            throw InvalidFileConverterConfigException::forInvalidClassInConfig($type, $class);
        }

        return $class;
    }
}
