<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Tools;

use App\Services\Ai\Chat\Values\Parts\ToolType;

/**
 * Tool selection behaviour: none / auto / any / force a specific tool.
 */
readonly class ToolChoice
{
    public function __construct(
        public ToolChoiceMode $mode = ToolChoiceMode::AUTO,
        public ?string $toolName = null,
        public ?ToolType $toolType = null,
    ) {
    }

    public static function auto(): self
    {
        return new self(mode: ToolChoiceMode::AUTO);
    }
}
