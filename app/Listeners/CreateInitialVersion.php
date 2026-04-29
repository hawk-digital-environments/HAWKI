<?php

namespace App\Listeners;

use App\Events\AssistantCreated;

class CreateInitialVersion
{
    public function handle(AssistantCreated $event): void
    {
        $event->assistant->versions()->create([
            'text' => 'Initial version',
            'version' => 1.0,
        ]);
    }
}
