<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Controls when the model produces reasoning / thinking output.
 */
enum ReasoningMode: string
{
    case AUTO = 'auto';
    case ENABLED = 'enabled';
    case DISABLED = 'disabled';
}
