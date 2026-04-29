<?php

namespace App\Listeners;

use App\Events\AssistantUpdated;

class CreateUpdatedVersion
{
    public function handle(AssistantUpdated $event): void
    {
        $lastVersion = $event->assistant->versions()->max('version') ?? 0.0;

        $event->assistant->versions()->create([
            'text' => $event->versionText ?? 'Updated',
            'version' => $lastVersion + 1.0,
        ]);
    }
}
