<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus\Concerns;

use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Parts\ContentPart;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\RefusalPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\UrlCitation;
use App\Services\Ai\Chat\Values\UsageInfo;

/**
 * Hydrates response-side fixtures (`irResponse` objects) into the typed
 * {@see AiResponse} IR, so Track 2 fixtures describe the IR declaratively.
 */
trait HydratesResponses
{
    public static function hydrateResponse(array $fixture): AiResponse
    {
        $parts = [];

        foreach ($fixture['message']['parts'] ?? [] as $part) {
            $parts[] = self::hydratePart($part);
        }

        $usage = null;

        if (isset($fixture['usage'])) {
            $usage = new UsageInfo(
                promptTokens: (int) ($fixture['usage']['promptTokens'] ?? 0),
                completionTokens: (int) ($fixture['usage']['completionTokens'] ?? 0),
                totalTokens: (int) ($fixture['usage']['totalTokens'] ?? ($fixture['usage']['promptTokens'] ?? 0) + ($fixture['usage']['completionTokens'] ?? 0)),
            );
        }

        return new AiResponse(
            id: (string) $fixture['id'],
            model: (string) $fixture['model'],
            created: (int) ($fixture['created'] ?? 1700000000),
            message: new AssistantMessage(parts: $parts),
            finishReason: new FinishReason(FinishReasonType::from((string) ($fixture['finishReason'] ?? 'stop'))),
            usage: $usage,
            systemFingerprint: $fixture['systemFingerprint'] ?? null,
        );
    }

    /**
     * @param array<string, mixed> $part
     */
    private static function hydratePart(array $part): ContentPart
    {
        return match ((string) ($part['type'] ?? '')) {
            'text' => TextPart::from((string) ($part['text'] ?? '')),
            'tool_call' => new ToolCallPart(
                toolCallId: (string) ($part['toolCallId'] ?? ''),
                toolName: (string) ($part['toolName'] ?? ''),
                toolInput: \is_array($part['toolInput'] ?? null) ? $part['toolInput'] : [],
                providerMetadata: $part['providerMetadata'] ?? null,
            ),
            'reasoning' => new ReasoningPart(
                reasoning: $part['reasoning'] ?? null,
                encryptedContent: $part['encryptedContent'] ?? null,
                providerMetadata: $part['providerMetadata'] ?? null,
            ),
            'citation' => new CitationPart(urlCitation: new UrlCitation(
                url: $part['url'] ?? null,
                title: $part['title'] ?? null,
                startIndex: $part['startIndex'] ?? null,
                endIndex: $part['endIndex'] ?? null,
            )),
            'refusal' => new RefusalPart((string) ($part['refusal'] ?? '')),
            default => throw new \InvalidArgumentException("Unknown fixture part type: " . ($part['type'] ?? '(none)')),
        };
    }
}
