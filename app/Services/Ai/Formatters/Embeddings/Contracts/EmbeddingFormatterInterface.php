<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Embeddings\Contracts;

use App\Services\Ai\Embeddings\Values\EmbeddingRequest;
use App\Services\Ai\Embeddings\Values\EmbeddingResponse;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A downstream spoke: translates one client wire format ⇄ the embeddings IR.
 *
 * The embeddings counterpart of {@see \App\Services\Ai\Formatters\Contracts\FormatterInterface} —
 * same pattern, minus streaming: embeddings do not stream. Implementations are
 * registered in the {@see \App\Services\Ai\Formatters\Embeddings\EmbeddingFormatterRegistry}
 * under a unique format key (e.g. 'openai').
 */
interface EmbeddingFormatterInterface
{
    /**
     * The format key this formatter handles (e.g. 'openai').
     */
    public function getKey(): string;

    /**
     * Parses a wire-format HTTP request into the embeddings request IR.
     *
     * @throws FormatterRequestException when the body cannot be parsed or violates
     *                                   format-level constraints
     */
    public function parseRequest(Request $request): EmbeddingRequest;

    /**
     * Formats the response IR into a wire-format HTTP response.
     */
    public function formatResponse(EmbeddingResponse $response): JsonResponse;

    /**
     * Renders a formatter-level request error in this format's error wire shape.
     */
    public function formatError(FormatterRequestException $exception): JsonResponse;
}
