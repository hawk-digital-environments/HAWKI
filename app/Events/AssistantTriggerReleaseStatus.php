<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\AssistantReleaseStage;

class AssistantTriggerReleaseStatus
{
    public function __construct(
        public readonly Assistant $assistant,
        public readonly AssistantReleaseStage $oldStage,
        public readonly AssistantReleaseStage $newStage,
    ) {
    }
}
