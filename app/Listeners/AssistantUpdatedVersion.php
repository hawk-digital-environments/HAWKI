<?php

namespace App\Listeners;

use App\Events\AssistantUpdated;
use App\Services\Assistant\Values\ReleaseStage;

class AssistantUpdatedVersion
{
    public function handle(AssistantUpdated $event): void
    {
        if ($event->assistant->release_stage === ReleaseStage::DRAFT->value) {
            return;
        }

        $lastVersion = $event->assistant->versions()->max('version') ?? 0.0;

        $event->assistant->versions()->create([
            'text' => $event->versionText ?? 'Updated',
            'version' => $lastVersion + 1.0,
            'changed_keys' => $event->changedKeys,
        ]);
    }
}
