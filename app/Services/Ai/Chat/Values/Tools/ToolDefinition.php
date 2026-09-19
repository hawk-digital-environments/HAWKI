<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Tools;

use App\Services\Ai\Chat\Values\Parts\ToolType;

/**
 * A callable tool offered to the model.
 */
readonly class ToolDefinition
{
    /**
     * @param array<string, mixed>      $parameters         JSON Schema describing the tool input
     * @param null|array<int, string>   $requiredParameters
     * @param null|array<string, mixed> $metadata
     * @param null|array<string, mixed> $cacheHint          provider cache breakpoint hints
     */
    public function __construct(
        public string $name,
        public string $description = '',
        public array $parameters = [],
        public ToolType $type = ToolType::FUNCTION,
        public ?array $requiredParameters = null,
        public ?array $metadata = null,
        public ?array $cacheHint = null,
    ) {
    }
}
