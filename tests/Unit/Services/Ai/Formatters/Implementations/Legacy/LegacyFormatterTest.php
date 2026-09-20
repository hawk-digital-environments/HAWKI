<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\Legacy;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Formatters\Corpus\Concerns\HydratesResponses;

#[CoversClass(LegacyFormatter::class)]
class LegacyFormatterTest extends TestCase
{
    use HydratesResponses;

    private LegacyFormatter $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new LegacyFormatter(
            userRepository: self::createStub(\App\Services\Users\Repositories\UserRepository::class),
            avatarStorage: self::createStub(\App\Services\Storage\AvatarStorageService::class),
        );
    }

    public function testItParsesTheLegacyPayload(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'broadcast' => false,
            'payload' => [
                'model' => 'gpt-4.1-nano',
                'stream' => true,
                'messages' => [
                    ['role' => 'system', 'content' => ['text' => 'Be terse.']],
                    ['role' => 'user', 'content' => ['text' => 'Hello']],
                    ['role' => 'assistant', 'content' => ['text' => 'Hi!']],
                    ['role' => 'user', 'content' => ['text' => 'What is 2+2?', 'attachments' => ['uuid-1', 'uuid-2']]],
                ],
                'tools' => ['capability:web_search:auto'],
                'params' => ['temp' => 0.1],
            ],
        ]));

        self::assertSame('gpt-4.1-nano', $parsed->model);
        self::assertSame('Be terse.', $parsed->systemInstructionText());
        self::assertTrue($parsed->wantsStreaming());
        self::assertSame(['capability:web_search:auto'], $parsed->hawkiExtension(AiRequest::HAWKI_EXTENSION_TOOLS));
        self::assertSame(['temp' => 0.1], $parsed->hawkiExtension(AiRequest::HAWKI_EXTENSION_PARAMS));
        self::assertNull($parsed->hawkiExtension(AiRequest::HAWKI_EXTENSION_BROADCAST));
        self::assertSame('legacy', $parsed->formatKey);

        // Three history messages (system hoisted); the last user turn carries its storage attachments.
        $last = $parsed->messages[2];
        self::assertCount(3, $last->parts);
        self::assertSame(AiRequest::HAWKI_STORAGE_SCHEME . 'uuid-1', $last->parts[1]->fileUrl);
        self::assertSame(AiRequest::HAWKI_STORAGE_SCHEME . 'uuid-2', $last->parts[2]->fileUrl);
    }

    public function testItParksBroadcastOnlyWhenTrue(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'broadcast' => true,
            'payload' => [
                'model' => 'm',
                'stream' => false,
                'messages' => [['role' => 'user', 'content' => ['text' => 'Hi']]],
            ],
        ]));

        self::assertTrue($parsed->hawkiExtension(AiRequest::HAWKI_EXTENSION_BROADCAST));
        self::assertFalse($parsed->wantsStreaming());
    }

    public function testItRequiresAModel(): void
    {
        try {
            $this->sut->parseRequest($this->request([
                'payload' => ['stream' => false, 'messages' => [['role' => 'user', 'content' => ['text' => 'Hi']]]],
            ]));
            self::fail('Expected InvalidInputItemException.');
        } catch (InvalidInputItemException $exception) {
            self::assertSame('missing_model', $exception->errorCode());
            self::assertSame('payload.model', $exception->param());
        }
    }

    public function testItRejectsATrailingAssistantMessage(): void
    {
        $this->expectException(InvalidInputItemException::class);
        self::assertSame('missing_user_turn', $this->tryParseErrorCode([
            'payload' => [
                'model' => 'm',
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hi']],
                    ['role' => 'assistant', 'content' => ['text' => 'Hello']],
                ],
            ],
        ]));
    }

    public function testItFormatsNonStreamingResponsesInTheLegacyShape(): void
    {
        $response = self::hydrateResponse([
            'id' => 'inv_1',
            'model' => 'm',
            'finishReason' => 'stop',
            'message' => ['parts' => [['type' => 'text', 'text' => 'Hello!']]],
        ]);

        $json = $this->sut->formatResponse($response);

        self::assertSame([
            'success' => true,
            'content' => '{"text":"Hello!"}',
        ], $json->getData(true));
    }

    public function testItRendersErrorsInTheLegacyShape(): void
    {
        $error = $this->sut->formatError(InvalidInputItemException::forMissingTrailingUserMessage());

        self::assertSame(400, $error->status());
        self::assertSame(false, $error->getData(true)['success']);
        self::assertArrayHasKey('message', $error->getData(true));
    }

    public function testItKeepsTheLegacyStreamHeaders(): void
    {
        $headers = $this->sut->getStreamHeaders();

        self::assertSame('text/event-stream', $headers['Content-Type']);
        self::assertSame('*', $headers['Access-Control-Allow-Origin']);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function request(array $body): Request
    {
        return Request::create(
            '/req/streamAI',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            (string) json_encode($body),
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function tryParseErrorCode(array $body): string
    {
        try {
            $this->sut->parseRequest($this->request($body));
        } catch (FormatterRequestException $exception) {
            self::assertSame('missing_user_turn', $exception->errorCode());

            throw $exception;
        }

        self::fail('Expected InvalidInputItemException.');
    }
}
