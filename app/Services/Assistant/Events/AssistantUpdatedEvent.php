<?php

declare(strict_types=1);

namespace App\Services\Assistant\Events;

use App\Models\Assistants\Assistant;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

readonly class AssistantUpdatedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<int, string> $changedKeys
     */
    public function __construct(
        public Assistant $assistant,
        public array $changedKeys = [],
    ) {
    }
}
