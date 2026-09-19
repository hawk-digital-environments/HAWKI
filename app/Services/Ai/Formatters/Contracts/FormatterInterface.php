<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Contracts;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A downstream spoke: translates one client wire format ↔ the chat IR.
 *
 * Implementations are registered in the {@see \App\Services\Ai\Formatters\FormatterRegistry}
 * under a unique format key (e.g. 'openResponses'). Plugins add their own formats by
 * declaring additional formatters there via `$app->extend()`.
 */
interface FormatterInterface
{
    /**
     * The format key this formatter handles (e.g. 'openResponses').
     */
    public function getKey(): string;

    /**
     * Parses a wire-format HTTP request into the chat request IR.
     *
     * @throws FormatterRequestException when the body cannot be parsed or violates
     *                                   format-level constraints (e.g. stateless-proxy restrictions).
     */
    public function parseRequest(Request $request): AiRequest;

    /**
     * Formats the response IR into a wire-format HTTP response (non-streaming).
     */
    public function formatResponse(AiResponse $response): JsonResponse;

    /**
     * Formats IR stream events into a wire-format streaming HTTP response.
     *
     * @param iterable<int, AiStreamEvent> $events
     */
    public function formatStream(iterable $events): StreamedResponse;

    /**
     * SSE headers for streaming responses.
     *
     * @return array<string, string>
     */
    public function getStreamHeaders(): array;

    /**
     * Renders a formatter-level request error in this format's error wire shape.
     */
    public function formatError(FormatterRequestException $exception): Response;
}
