<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus\Concerns;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\AudioAsset;
use App\Services\Ai\Chat\Values\Parts\AudioPart;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\ContentPart;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\RefusalPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\ToolResultPart;
use App\Services\Ai\Chat\Values\Parts\UrlCitation;

/**
 * Normalizes the typed chat IR into comparable arrays for corpus snapshots.
 *
 * Rules (see N5-Handoff §4): null fields are omitted recursively, comparison is
 * key-order-insensitive (canonicalized), and formatter-generated uuids are replaced
 * by `<uuid>` placeholders so fixtures can pin id prefixes without pinning values.
 */
trait NormalizesIr
{
    /**
     * @return array<string, mixed>
     */
    public static function normalizeRequest(AiRequest $request): array
    {
        return self::pruneNulls([
            'model' => $request->model,
            'formatKey' => $request->formatKey,
            'messages' => array_map(static fn (Message $message): array => self::normalizeMessage($message), $request->messages),
            'systemInstruction' => null !== $request->systemInstruction
                ? array_map(static fn (TextPart $part): array => self::pruneNulls(['type' => 'text', 'text' => $part->text]), $request->systemInstruction)
                : null,
            'tools' => null !== $request->tools ? array_map(static fn (\App\Services\Ai\Chat\Values\Tools\ToolDefinition $tool): array => self::pruneNulls([
                'name' => $tool->name,
                'description' => '' !== $tool->description ? $tool->description : null,
                'parameters' => $tool->parameters,
                'metadata' => $tool->metadata,
            ]), $request->tools) : null,
            'toolChoice' => null !== $request->toolChoice ? self::pruneNulls([
                'mode' => $request->toolChoice->mode->value,
                'toolName' => $request->toolChoice->toolName,
            ]) : null,
            'toolConfig' => null !== $request->toolConfig ? self::pruneNulls([
                'disableParallel' => $request->toolConfig->disableParallel,
                'maxCalls' => $request->toolConfig->maxCalls,
            ]) : null,
            'generation' => null !== $request->generation ? self::pruneNulls([
                'temperature' => $request->generation->temperature,
                'topP' => $request->generation->topP,
                'maxTokens' => $request->generation->maxTokens,
                'frequencyPenalty' => $request->generation->frequencyPenalty,
                'presencePenalty' => $request->generation->presencePenalty,
                'truncation' => $request->generation->truncation,
                'stopSequences' => $request->generation->stopSequences,
                'logitBias' => $request->generation->logitBias,
                'seed' => $request->generation->seed,
                'logprobs' => $request->generation->logprobs,
                'topLogprobs' => $request->generation->topLogprobs,
            ]) : null,
            'responseFormat' => null !== $request->responseFormat ? self::pruneNulls([
                'type' => $request->responseFormat->type->value,
                'name' => $request->responseFormat->name,
                'strict' => $request->responseFormat->strict,
                'jsonSchema' => $request->responseFormat->jsonSchema,
            ]) : null,
            'stream' => [
                'enabled' => $request->stream->enabled,
                'includeUsage' => $request->stream->includeUsage,
            ],
            'reasoning' => null !== $request->reasoning ? self::pruneNulls([
                'mode' => $request->reasoning->mode?->value,
                'effort' => $request->reasoning->effort?->value,
                'summary' => $request->reasoning->summary?->value,
            ]) : null,
            'providerExtensions' => $request->providerExtensions,
            'hawkiExtensions' => $request->hawkiExtensions,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizeMessage(Message $message): array
    {
        $role = match (true) {
            $message instanceof UserMessage => 'user',
            $message instanceof AssistantMessage => 'assistant',
            $message instanceof ToolMessage => 'tool',
            default => 'system',
        };

        return [
            'role' => $role,
            'parts' => array_map(static fn (ContentPart $part): array => self::normalizePart($part), \is_array($message->parts) ? $message->parts : []),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function normalizePart(ContentPart $part): array
    {
        if ($part instanceof TextPart) {
            return self::pruneNulls(['type' => 'text', 'text' => $part->text]);
        }

        if ($part instanceof ImagePart) {
            return self::pruneNulls([
                'type' => 'image',
                'imageUrl' => $part->imageUrl,
                'detail' => $part->detail,
                'imageData' => null !== $part->imageData ? ['data' => $part->imageData->data, 'mediaType' => $part->imageData->mediaType] : null,
            ]);
        }

        if ($part instanceof FilePart) {
            return self::pruneNulls([
                'type' => 'file',
                'fileUrl' => $part->fileUrl,
                'fileName' => $part->fileName,
                'fileType' => $part->fileType,
            ]);
        }

        if ($part instanceof AudioPart) {
            return self::pruneNulls([
                'type' => 'audio',
                'url' => $part->url,
                'audioData' => null !== $part->audioData ? ['data' => $part->audioData->data, 'mediaType' => $part->audioData->mediaType] : null,
            ]);
        }

        if ($part instanceof ToolCallPart) {
            return self::pruneNulls([
                'type' => 'tool_call',
                'toolCallId' => $part->toolCallId,
                'toolName' => $part->toolName,
                'toolInput' => $part->toolInput,
                'providerMetadata' => $part->providerMetadata,
            ]);
        }

        if ($part instanceof ToolResultPart) {
            return self::pruneNulls([
                'type' => 'tool_result',
                'toolCallId' => $part->toolCallId,
                'result' => $part->result,
            ]);
        }

        if ($part instanceof ReasoningPart) {
            return self::pruneNulls([
                'type' => 'reasoning',
                'reasoning' => $part->reasoning,
                'encryptedContent' => $part->encryptedContent,
                'providerMetadata' => $part->providerMetadata,
            ]);
        }

        if ($part instanceof CitationPart) {
            return self::pruneNulls([
                'type' => 'citation',
                'urlCitation' => null !== $part->urlCitation ? self::pruneNulls([
                    'url' => $part->urlCitation->url,
                    'title' => $part->urlCitation->title,
                    'startIndex' => $part->urlCitation->startIndex,
                    'endIndex' => $part->urlCitation->endIndex,
                ]) : null,
            ]);
        }

        if ($part instanceof RefusalPart) {
            return self::pruneNulls(['type' => 'refusal', 'refusal' => $part->refusal]);
        }

        return ['type' => 'unknown', 'class' => $part::class];
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    public static function pruneNulls(array $value): array
    {
        return array_filter($value, static fn (mixed $entry): bool => null !== $entry);
    }

    /**
     * Key-order-insensitive, uuid-placeholder comparison of IR snapshots.
     *
     * @param array<string, mixed> $expected
     * @param array<string, mixed> $actual
     */
    public static function assertIrSnapshotEquals(array $expected, array $actual, string $context): void
    {
        self::assertSame(
            self::canonicalize(self::replaceGeneratedIds($expected)),
            self::canonicalize(self::replaceGeneratedIds($actual)),
            "IR snapshot mismatch ({$context})",
        );
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function canonicalize(array $value): array
    {
        ksort($value);

        foreach ($value as $key => $entry) {
            if (\is_array($entry)) {
                $value[$key] = self::canonicalize($entry);
            }
        }

        return $value;
    }

    /**
     * Formatter-generated item ids (msg_/fc_/rs_/cit_ + uuid) become placeholders so
     * fixtures pin the prefix without pinning the value.
     *
     * @param array<string, mixed> $value
     *
     * @return array<string, mixed>
     */
    private static function replaceGeneratedIds(array $value): array
    {
        foreach ($value as $key => $entry) {
            if (\is_array($entry)) {
                $value[$key] = self::replaceGeneratedIds($entry);
            } elseif (\is_string($entry) && preg_match('/^(msg_|fc_|rs_|cit_)[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $entry)) {
                $value[$key] = preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', '<uuid>', $entry);
            }
        }

        return $value;
    }
}
