<?php

declare(strict_types=1);

namespace App\Services\Assistant\Chat;

use Generator;

interface AssistantChatRunnerInterface
{
    /**
     * @return Generator<array{type: string, content: mixed}>
     */
    public function stream(
        string $systemPrompt,
        array $messages,
        string $model,
        array $tools = [],
        array $params = [],
    ): Generator;
}
