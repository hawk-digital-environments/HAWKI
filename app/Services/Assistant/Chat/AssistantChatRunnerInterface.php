<?php

declare(strict_types=1);

namespace App\Services\Assistant\Chat;

use Generator;

interface AssistantChatRunnerInterface
{
    /**
     * @param  callable|null  $sink  Real-time callback invoked as chunks are collected via the AI callback.
     *                                 When set, the chunk is pushed immediately without waiting for the full stream.
     * @return Generator<array{type: string, content: mixed}>
     */
    public function stream(
        string $systemPrompt,
        array $messages,
        string $model,
        array $tools = [],
        array $params = [],
        ?callable $sink = null,
    ): Generator;
}
