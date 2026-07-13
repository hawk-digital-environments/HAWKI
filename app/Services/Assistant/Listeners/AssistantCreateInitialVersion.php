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
        $version = (new AssistantVersion())->forceFill([
            'text' => json_encode(['changes' => []], \JSON_THROW_ON_ERROR),
            'version' => 1.0,
        ]);
        $event->assistant->assistantVersions()->save($version);
    }
}
