<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Reasoning summary verbosity as requested by the client.
 */
enum ReasoningSummary: string
{
    case AUTO = 'auto';
    case CONCISE = 'concise';
    case DETAILED = 'detailed';
    case NONE = 'none';
}
