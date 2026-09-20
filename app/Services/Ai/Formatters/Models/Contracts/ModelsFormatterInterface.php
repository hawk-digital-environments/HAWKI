<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Models\Contracts;

use App\Models\Ai\AiModel;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

/**
 * A downstream spoke: renders the model catalogue in one client wire format.
 *
 * The models counterpart of {@see \App\Services\Ai\Formatters\Contracts\FormatterInterface} —
 * same pattern, minus parsing (the endpoint is a read-only GET) and streaming.
 * Implementations are registered in the
 * {@see \App\Services\Ai\Formatters\Models\ModelsFormatterRegistry} under a unique
 * format key (e.g. 'openai').
 */
interface ModelsFormatterInterface
{
    /**
     * The format key this formatter handles (e.g. 'openai').
     */
    public function getKey(): string;

    /**
     * Formats the visible model catalogue into a wire-format HTTP response.
     *
     * @param Collection<int, AiModel> $models provider-loaded, deterministically ordered
     */
    public function formatModels(Collection $models): JsonResponse;

    /**
     * Renders a formatter-level error in this format's error wire shape.
     */
    public function formatError(FormatterRequestException $exception): JsonResponse;
}
