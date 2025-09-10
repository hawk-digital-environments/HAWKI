<?php

namespace App\Services\FileConverter;

use App\Services\FileConverter\Handlers\GwdgDocling;
use App\Services\FileConverter\Handlers\HawkiDocConverter;
use App\Services\FileConverter\Handlers\Interfaces\FileConverterInterface;
use Exception;
class FileConverterFactory
{
    /**
     * @throws Exception
     */
    public static function create(string $type = null): FileConverterInterface
    {
        $type = $type ?? config('file_converter.default');
        $converters = config('file_converter.converters');

        // If requested type is inactive → fallback to default
        if (!self::isActive($type)) {
            $fallback = config('file_converter.fallback');
            if(!empty($fallback)){
                if (!self::isActive($fallback)) {
                    throw new Exception("No active file converter available. Tried $type and fallback $fallback.");
                }
                $type = $fallback;
            }
        }

        return match ($type) {
            'hawki_converter' => new HawkiDocConverter($converters[$type]),
            'gwdg_docling' => new GwdgDocling($converters[$type]),
            default => throw new Exception("Unknown file converter type: $type."),
        };
    }

    public static function isActive(string $type): bool
    {
        $converters = config('file_converter.converters');
        $config = $converters[$type] ?? null;

        if (!$config) {
            return false;
        }

        return !empty($config['api_url'])
            && $config['api_url'] !== ""
            && !empty($config['api_key'])
            && $config['api_key'] !== "";
    }


    public static function converterActive(): bool
    {
        $default = config('file_converter.default');
        $fallback = config('file_converter.fallback');

        if(self::isActive($default)) {
            return true;
        }
        if(self::isActive($fallback)) {
            return true;
        }

        return false;
    }
}

