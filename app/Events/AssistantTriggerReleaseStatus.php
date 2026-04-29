<?php

namespace App\Events;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\ReleaseStage;

class AssistantTriggerReleaseStatus
{
    public function __construct(
        public readonly Assistant $assistant,
        public readonly ReleaseStage $oldStage,
        public readonly ReleaseStage $newStage,
    ) {}
}
