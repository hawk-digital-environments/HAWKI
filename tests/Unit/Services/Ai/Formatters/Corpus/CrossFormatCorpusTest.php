<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus;

use App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions\OpenAiChatCompletionsFormatter;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\LoadsCorpusFixtures;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\NormalizesIr;

/**
 * Track 4 — cross-format IR equivalence: the same logical conversation expressed in
 * both wire dialects parses to structurally equal IR (the hub-and-spoke promise).
 *
 * Comparison strips the per-dialect fields by design: `formatKey`,
 * `providerExtensions` (each dialect parks its own unmappables), and
 * `stream.includeUsage` (dialect defaults differ).
 */
#[CoversClass(OpenResponsesFormatter::class)]
#[CoversClass(OpenAiChatCompletionsFormatter::class)]
class CrossFormatCorpusTest extends TestCase
{
    use LoadsCorpusFixtures;
    use NormalizesIr;

    private OpenResponsesFormatter $openResponses;

    private OpenAiChatCompletionsFormatter $chatCompletions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openResponses = new OpenResponsesFormatter();
        $this->chatCompletions = new OpenAiChatCompletionsFormatter();
    }

    /**
     */
    #[DataProvider('crossFormatFixtures')]
    public function testBothDialectsParseToEqualIr(string $path): void
    {
        $fixture = self::loadFixture($path);

        $openResponsesIr = self::normalizeRequest($this->openResponses->parseRequest($this->request($fixture['openResponses'])));
        $chatCompletionsIr = self::normalizeRequest($this->chatCompletions->parseRequest($this->request($fixture['openai'])));

        self::assertIrSnapshotEquals(
            self::stripDialectFields($openResponsesIr),
            self::stripDialectFields($chatCompletionsIr),
            basename($path),
        );
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function crossFormatFixtures(): array
    {
        return self::corpusProvider('cross_format');
    }

    /**
     * @param array<string, mixed> $normalized
     *
     * @return array<string, mixed>
     */
    private static function stripDialectFields(array $normalized): array
    {
        unset($normalized['formatKey'], $normalized['providerExtensions']);
        $normalized['stream'] = ['enabled' => $normalized['stream']['enabled'] ?? false];
        $normalized['messages'] = self::coalesceAssistantRuns($normalized['messages'] ?? []);
        $normalized['messages'] = self::stripReasoningReplayState($normalized['messages']);

        return $normalized;
    }

    /**
     * Replay-state metadata (encryptedContent, provider item ids) is expressible only
     * in the openResponses dialect — Chat Completions' reasoning_content carries text
     * alone. Strip it from the comparison rather than weakening the fixtures.
     *
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<int, array<string, mixed>>
     */
    private static function stripReasoningReplayState(array $messages): array
    {
        foreach ($messages as &$message) {
            if (!isset($message['parts']) || !\is_array($message['parts'])) {
                continue;
            }

            foreach ($message['parts'] as &$part) {
                if ('reasoning' === ($part['type'] ?? '')) {
                    unset($part['encryptedContent'], $part['providerMetadata']);
                }
            }
            unset($part);
        }

        return $messages;
    }

    /**
     * The dialects partition one logical assistant turn differently (openResponses:
     * a reasoning item followed by a function_call item; Chat Completions: one
     * message with both fields) — coalesce consecutive assistant messages so the
     * comparison is over the logical conversation, mirroring what the agent factory
     * does when replaying.
     *
     * @param array<int, array<string, mixed>> $messages
     *
     * @return array<int, array<string, mixed>>
     */
    private static function coalesceAssistantRuns(array $messages): array
    {
        $coalesced = [];

        foreach ($messages as $message) {
            $previous = &$coalesced[array_key_last($coalesced)] ?? null;

            if ('assistant' === ($message['role'] ?? '') && null !== $previous && 'assistant' === ($previous['role'] ?? '')) {
                $previous['parts'] = [...$previous['parts'], ...$message['parts']];

                continue;
            }

            $coalesced[] = $message;
            unset($previous);
        }

        return $coalesced;
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create(
            '/api/hawki/v1/chat',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }
}
