<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

/**
 * Normalized reason a generation ended.
 */
enum FinishReasonType: string
{
    case STOP = 'stop';
    case LENGTH = 'length';
    case TOOL_CALLS = 'tool_calls';
    case CONTENT_FILTER = 'content_filter';
    case REFUSAL = 'refusal';
    case ERROR = 'error';
    case CANCELLED = 'cancelled';
}
