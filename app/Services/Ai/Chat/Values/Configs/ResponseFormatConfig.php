<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Structured-output configuration ({@see ResponseFormatType::JSON_SCHEMA} carries the
 * schema; the other flavours only carry the type).
 */
readonly class ResponseFormatConfig
{
    /**
     * @param null|array<string, mixed> $jsonSchema JSON Schema definition
     */
    public function __construct(
        public ResponseFormatType $type = ResponseFormatType::TEXT,
        public ?array $jsonSchema = null,
        public ?string $name = null,
        public ?bool $strict = null,
        public ?string $mimeType = null,
    ) {
    }
}
