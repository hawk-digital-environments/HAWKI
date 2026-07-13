<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Assistants\Assistant;

class AssistantCreated
{
    public function __construct(public readonly Assistant $assistant)
    {
    }
}
