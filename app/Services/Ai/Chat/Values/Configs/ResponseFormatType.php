<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Structured-output format requested for the response.
 */
enum ResponseFormatType: string
{
    case TEXT = 'text';
    case JSON_OBJECT = 'json_object';
    case JSON_SCHEMA = 'json_schema';
}
