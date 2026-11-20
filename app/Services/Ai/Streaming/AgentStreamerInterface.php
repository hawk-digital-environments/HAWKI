<?php

declare(strict_types=1);

namespace App\Services\Ai\Streaming;

interface AgentStreamerInterface
{
    /**
     * @param null|callable $sink Real-time callback invoked as chunks are collected via the AI callback.
     *                            When set, the chunk is pushed immediately without waiting for the full stream.
     * @param string|null   $assistantHandle Optional assistant handle pinning the exchange to an
     *                                       assistant-driven run; forwarded into the legacy payload so the
     *                                       assistant agent factory resolves it explicitly.
     *
     * @return \Generator<array{type: string, content: mixed}>
     */
    public function stream(
        array $messages,
        string $model,
        array $tools = [],
        array $params = [],
        ?callable $sink = null,
        ?string $assistantHandle = null,
    ): \Generator;
}
