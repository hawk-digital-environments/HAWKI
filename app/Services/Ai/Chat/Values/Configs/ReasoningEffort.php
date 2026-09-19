<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Configs;

/**
 * Reasoning effort ladder, ordered from cheapest to most thorough. The union of the
 * effort vocabularies across the supported wire formats.
 */
enum ReasoningEffort: string
{
    case MINIMAL = 'minimal';
    case LOW = 'low';
    case MEDIUM = 'medium';
    case HIGH = 'high';
    case XHIGH = 'xhigh';
    case MAX = 'max';
}
