<?php

declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Events\AssistantCreated;

class AssistantCreateInitialVersion
{
    public function handle(AssistantCreated $event): void
    {
        if ($event->assistant->assistantVersions()->exists()) {
            return;
        }

        $event->assistant->assistantVersions()->create([
            'text' => json_encode(['changes' => []]),
            'version' => 1.0,
        ]);
    }
}
