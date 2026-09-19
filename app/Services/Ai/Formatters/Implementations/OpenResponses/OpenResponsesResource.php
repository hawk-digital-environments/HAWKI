<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\OpenResponses;

use App\Services\Ai\Chat\Values\UsageInfo;

/**
 * Assembles the OpenAI Responses API {@see ResponseResource} JSON shape.
 *
 * Every field of the resource is required by the Open Responses specification (most
 * nullable) — this builder guarantees the full 31-field skeleton is always emitted,
 * both for non-streaming responses and for the response snapshots carried by the
 * lifecycle SSE events.
 */
final class OpenResponsesResource
{
    /**
     * @param array<int, array<string, mixed>>           $output            completed output items
     * @param null|array<string, mixed>                  $incompleteDetails
     * @param null|array{code: ?string, message: string} $error
     * @param null|array<string, mixed>                  $metadata
     */
    public static function resource(
        string $id,
        string $model,
        int $createdAt,
        array $output,
        string $status = 'in_progress',
        ?array $incompleteDetails = null,
        ?array $error = null,
        ?array $metadata = null,
        ?UsageInfo $usage = null,
    ): array {
        return [
            'id' => $id,
            'object' => 'response',
            'created_at' => $createdAt,
            'completed_at' => 'in_progress' === $status ? null : $createdAt,
            'status' => $status,
            'incomplete_details' => $incompleteDetails,
            'error' => $error,
            'model' => $model,
            'previous_response_id' => null,
            'instructions' => null,
            'output' => array_values($output),
            'tools' => [],
            'tool_choice' => 'auto',
            'truncation' => 'disabled',
            'parallel_tool_calls' => true,
            'text' => ['format' => ['type' => 'text'], 'verbosity' => null],
            'temperature' => null,
            'top_p' => null,
            'presence_penalty' => null,
            'frequency_penalty' => null,
            'top_logprobs' => null,
            'reasoning' => ['effort' => null, 'summary' => null],
            'usage' => self::usage($usage),
            'max_output_tokens' => null,
            'max_tool_calls' => null,
            'store' => false,
            'background' => false,
            'service_tier' => null,
            'metadata' => $metadata ?? new \stdClass(),
            'safety_identifier' => null,
            'prompt_cache_key' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function usage(?UsageInfo $usage): array
    {
        if (null === $usage) {
            return [
                'input_tokens' => 0,
                'output_tokens' => 0,
                'total_tokens' => 0,
                'input_tokens_details' => ['cached_tokens' => 0],
                'output_tokens_details' => ['reasoning_tokens' => 0],
            ];
        }

        return [
            'input_tokens' => $usage->promptTokens,
            'output_tokens' => $usage->completionTokens,
            'total_tokens' => $usage->totalTokens,
            'input_tokens_details' => ['cached_tokens' => $usage->cacheReadTokens ?? 0],
            'output_tokens_details' => ['reasoning_tokens' => $usage->reasoningTokens ?? 0],
        ];
    }
}
