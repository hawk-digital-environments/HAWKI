<?php

namespace App\Events;

use App\Models\Assistants\Assistant;

class AssistantCreated
{
    public function __construct(
        public readonly Assistant $assistant,
    ) {}
}
