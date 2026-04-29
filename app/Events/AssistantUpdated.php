<?php

namespace App\Events;

use App\Models\Assistants\Assistant;

class AssistantUpdated
{
    public function __construct(
        public readonly Assistant $assistant,
        public readonly ?string $versionText = null,
    ) {}
}
