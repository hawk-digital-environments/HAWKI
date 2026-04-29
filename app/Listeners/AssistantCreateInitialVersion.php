<?php

namespace App\Listeners;

use App\Events\AssistantCreated;

class AssistantCreateInitialVersion
{
    public function handle(AssistantCreated $event): void
    {
        if ($event->assistant->versions()->exists()) {
            return;
        }

        $event->assistant->versions()->create([
            'text' => json_encode(['changes' => []]),
            'version' => 1.0,
        ]);
    }
}
