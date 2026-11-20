<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Models\Assistants\AssistantVersion;
use App\Services\Assistant\AssistantVersionService;
use App\Services\Assistant\Events\AssistantCreatedEvent;

class AssistantCreateInitialVersion
{
    public function __construct(private readonly AssistantVersionService $versionService)
    {
    }

    public function handle(AssistantCreatedEvent $event): void
    {
        if ($event->assistant->assistantVersions()->exists()) {
            return;
        }

        // Every assistant starts with the mutable working-draft row at 0.0;
        // the first approval promotes it to 1.0. `version` and `payload` are
        // server-controlled; `text` carries the creator's release note.
        $version = (new AssistantVersion())->forceFill([
            'text' => '',
            'version' => AssistantVersion::DRAFT_VERSION,
            'payload' => $this->versionService->capture($event->assistant),
        ]);
        $event->assistant->assistantVersions()->save($version);
    }
}
