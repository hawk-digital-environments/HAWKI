<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values\Stream;

use App\Services\Ai\Chat\Values\FinishReason;

/**
 * Generation finished with the given reason.
 */
readonly class FinishEvent implements AiStreamEvent
{
    public const string TYPE = 'finish';

    public function __construct(public FinishReason $finishReason)
    {
    }
}
