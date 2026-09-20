<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus;

use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesFormatter;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesStreamContext;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\HydratesResponses;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\LoadsCorpusFixtures;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\NormalizesIr;

/**
 * Round-trip corpus for the `openResponses` formatter (N5, proposal §9.1 as
 * reinterpretated in N5-Handoff §2): parse-fidelity snapshots (Track 1),
 * response-fidelity snapshots (Track 2), and the emit-replay cycle (Track 3).
 */
#[CoversClass(OpenResponsesFormatter::class)]
#[CoversClass(OpenResponsesStreamContext::class)]
class OpenResponsesCorpusTest extends TestCase
{
    use HydratesResponses;
    use LoadsCorpusFixtures;
    use NormalizesIr;

    private OpenResponsesFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new OpenResponsesFormatter();
    }

    /**
     * Track 1 — wire request fixtures parse to the expected IR snapshot.
     *
     */
    #[DataProvider('openResponsesRequestFixtures')]
    public function testRequestFixtureParsesToItsIrSnapshot(string $path): void
    {
        $fixture = self::loadFixture($path);

        try {
            $parsed = $this->formatter->parseRequest($this->request($fixture['wireRequest']));
        } catch (FormatterRequestException $exception) {
            self::assertErrorMatches($fixture['expectedError'] ?? null, $exception, $path);

            return;
        }

        self::assertArrayNotHasKey('expectedError', $fixture, "Fixture {$path} expected an error but the request parsed.");

        self::assertIrSnapshotEquals($fixture['expectedIr'], self::normalizeRequest($parsed), basename($path));
    }

    /**
     * Track 2 — IR response fixtures format to the expected wire snapshot.
     *
     */
    #[DataProvider('openResponsesResponseFixtures')]
    public function testResponseFixtureFormatsToItsWireSnapshot(string $path): void
    {
        $fixture = self::loadFixture($path);

        $wire = $this->formatter->formatResponse(self::hydrateResponse($fixture['irResponse']))->getData(true);

        self::assertIrSnapshotEquals($fixture['expectedWireResponse'], $wire, basename($path));
    }

    /**
     * Track 3 — the emit-replay cycle: formatted output items are valid input items,
     * and the loop-relevant state survives re-parsing (the client-tool-loop guarantee).
     *
     */
    #[DataProvider('openResponsesResponseFixtures')]
    public function testFormattedOutputReplaysAsInput(string $path): void
    {
        $fixture = self::loadFixture($path);
        $output = $this->formatter->formatResponse(self::hydrateResponse($fixture['irResponse']))->getData(true)['output'];

        if ([] === $output) {
            self::markTestSkipped('Fixture produces no output items to replay.');
        }

        $input = [...$output, ['type' => 'message', 'role' => 'user', 'content' => 'continue']];

        $parsed = $this->formatter->parseRequest($this->request(['model' => 'gpt-test', 'input' => $input]));

        $assistantText = '';
        $toolCalls = [];
        $reasoningParts = [];

        foreach ($parsed->messages as $message) {
            if ($message instanceof AssistantMessage) {
                $assistantText .= $message->text();

                foreach ($message->parts as $part) {
                    if ($part instanceof \App\Services\Ai\Chat\Values\Parts\ReasoningPart) {
                        $reasoningParts[] = $part;
                    }
                }

                foreach ($message->toolCalls() as $part) {
                    $toolCalls[] = $part;
                }
            }
        }

        foreach ($output as $item) {
            if ('message' === ($item['type'] ?? null)) {
                foreach ($item['content'] ?? [] as $part) {
                    if ('output_text' === ($part['type'] ?? null)) {
                        self::assertStringContainsString((string) $part['text'], $assistantText, 'Emitted text must survive replay for ' . basename($path));
                    }
                }
            }

            if ('function_call' === ($item['type'] ?? null)) {
                $match = null;

                foreach ($toolCalls as $toolCall) {
                    if ($toolCall->toolCallId === ($item['call_id'] ?? null)) {
                        $match = $toolCall;
                    }
                }

                self::assertNotNull($match, 'Emitted function_call must survive replay for ' . basename($path));
                self::assertSame($item['name'] ?? null, $match->toolName);
                self::assertEquals(json_decode((string) ($item['arguments'] ?? '{}'), true), $match->toolInput);
            }

            if ('reasoning' === ($item['type'] ?? null) && null !== ($item['encrypted_content'] ?? null)) {
                $match = null;

                foreach ($reasoningParts as $reasoningPart) {
                    if (($reasoningPart->providerMetadata['item_id'] ?? null) === ($item['id'] ?? null)) {
                        $match = $reasoningPart;
                    }
                }

                self::assertNotNull($match, 'Emitted reasoning item must survive replay for ' . basename($path));
                self::assertSame($item['encrypted_content'], $match->encryptedContent);
            }
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function openResponsesRequestFixtures(): array
    {
        return self::corpusProvider('openai_responses/requests');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function openResponsesResponseFixtures(): array
    {
        return self::corpusProvider('openai_responses/responses');
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

    /**
     * @param array<string, mixed>|null $expected
     */
    private static function assertErrorMatches(?array $expected, FormatterRequestException $exception, string $path): void
    {
        self::assertNotNull($expected, "Fixture {$path} parsed with an unexpected error: {$exception->errorCode()} — {$exception->getMessage()}");
        self::assertSame($expected['code'], $exception->errorCode(), basename($path));

        if (\array_key_exists('param', $expected)) {
            self::assertSame($expected['param'], $exception->param(), basename($path));
        }

        if (\array_key_exists('httpStatus', $expected)) {
            self::assertSame($expected['httpStatus'], $exception->httpStatus(), basename($path));
        }
    }
}
