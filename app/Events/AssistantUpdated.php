<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Assistants\Assistant;

class AssistantUpdated
{
    public function __construct(
        public readonly Assistant $assistant,
        public readonly ?string $versionText = null,
        public readonly array $changedKeys = [],
    ) {
    }
}
