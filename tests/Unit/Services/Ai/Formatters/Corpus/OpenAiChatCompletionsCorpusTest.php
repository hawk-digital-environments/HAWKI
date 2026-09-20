<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Corpus;

use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions\OpenAiChatCompletionsFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\HydratesResponses;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\LoadsCorpusFixtures;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\NormalizesIr;

/**
 * Round-trip corpus for the `openai` (Chat Completions) formatter (N5): parse-fidelity
 * snapshots (Track 1) and response-fidelity snapshots (Track 2). The emit-replay cycle
 * (Track 3) is openResponses-only — Chat Completions responses are not valid request
 * payloads.
 */
#[CoversClass(OpenAiChatCompletionsFormatter::class)]
class OpenAiChatCompletionsCorpusTest extends TestCase
{
    use HydratesResponses;
    use LoadsCorpusFixtures;
    use NormalizesIr;

    private OpenAiChatCompletionsFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new OpenAiChatCompletionsFormatter();
    }

    /**
     * Track 1 — wire request fixtures parse to the expected IR snapshot.
     *
     */
    #[DataProvider('chatCompletionsRequestFixtures')]
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
    #[DataProvider('chatCompletionsResponseFixtures')]
    public function testResponseFixtureFormatsToItsWireSnapshot(string $path): void
    {
        $fixture = self::loadFixture($path);

        $wire = $this->formatter->formatResponse(self::hydrateResponse($fixture['irResponse']))->getData(true);

        self::assertIrSnapshotEquals($fixture['expectedWireResponse'], $wire, basename($path));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function chatCompletionsRequestFixtures(): array
    {
        return self::corpusProvider('openai_chat_completions/requests');
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function chatCompletionsResponseFixtures(): array
    {
        return self::corpusProvider('openai_chat_completions/responses');
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create(
            '/api/hawki/v1/chat/openai',
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
