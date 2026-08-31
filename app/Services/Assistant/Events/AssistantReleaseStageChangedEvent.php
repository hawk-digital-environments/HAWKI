<?php

declare(strict_types=1);

namespace App\Services\Assistant\Events;

use App\Models\Assistants\Assistant;
use App\Services\Assistant\Values\AssistantReleaseStage;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

readonly class AssistantReleaseStageChangedEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public Assistant $assistant,
        public AssistantReleaseStage $oldStage,
        public AssistantReleaseStage $newStage,
    ) {
    }
}
