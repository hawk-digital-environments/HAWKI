<?php

declare(strict_types=1);

namespace App\Services\AI\Stream;

interface StreamAdapterInterface
{
    /**
     * Transform a runner chunk into protocol-specific SSE lines.
     *
     * @param  array{type: string, content: mixed}  $chunk  Runner chunk (text_delta, tool_call, tool_result, status, usage).
     * @return iterable<string>  One or more formatted SSE lines.
     */
    public function transform(array $chunk): iterable;

    /**
     * Get HTTP headers required for this streaming protocol.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array;

    /**
     * Protocol initialization sequence emitted at stream start.
     *
     * @return iterable<string>
     */
    public function start(): iterable;

    /**
     * Protocol termination sequence emitted at stream end (success).
     *
     * @return iterable<string>
     */
    public function end(): iterable;

    /**
     * Protocol-specific error formatting emitted on stream failure.
     *
     * @return iterable<string>
     */
    public function error(\Throwable $e): iterable;
}
