<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

use App\Services\Ai\Chat\Values\UsageInfo;

/**
 * Token usage for the generation (usually emitted once, near the end of the stream).
 */
readonly class UsageEvent implements AiStreamEvent
{
    public const string TYPE = 'usage';

    public function __construct(public UsageInfo $usage)
    {
    }
}
