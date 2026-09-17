<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\Events\AssistantCreatedEvent;

class AssistantCreateInitialVersion
{
    public function handle(AssistantCreatedEvent $event): void
    {
        if ($event->assistant->assistantVersions()->exists()) {
            return;
        }

        // version is server-controlled and intentionally not mass-assignable.
        // `text` carries the creator's release note (written by the release
        // action); the initial version is created without one.
        $version = (new AssistantVersion())->forceFill([
            'text' => '',
            'version' => 1.0,
        ]);
        $event->assistant->assistantVersions()->save($version);
    }
}
