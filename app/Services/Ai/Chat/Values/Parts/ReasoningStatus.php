<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Parts;

/**
 * Lifecycle status of a reasoning trace.
 */
enum ReasoningStatus: string
{
    case PENDING = 'pending';
    case COMPLETED = 'completed';
}
