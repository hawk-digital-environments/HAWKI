<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\OpenAiChatCompletions;

use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatType;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\ImagePart;
use App\Services\Ai\Chat\Values\Parts\ReasoningPart;
use App\Services\Ai\Chat\Values\Parts\RefusalPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\ToolResultPart;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Exceptions\InvalidRequestBodyException;
use App\Services\Ai\Formatters\Implementations\OpenAiChatCompletions\OpenAiChatCompletionsFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenAiChatCompletionsFormatter::class)]
class OpenAiChatCompletionsFormatterTest extends TestCase
{
    private OpenAiChatCompletionsFormatter $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new OpenAiChatCompletionsFormatter();
    }

    public function testItParsesASimpleConversation(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'gpt-4.1-nano',
            'messages' => [
                ['role' => 'system', 'content' => 'Be terse.'],
                ['role' => 'user', 'content' => 'Hello'],
            ],
        ]));

        self::assertSame('gpt-4.1-nano', $parsed->model);
        self::assertSame('Be terse.', $parsed->systemInstructionText());
        self::assertCount(1, $parsed->messages);
        self::assertInstanceOf(UserMessage::class, $parsed->messages[0]);
        self::assertSame('Hello', $parsed->messages[0]->text());
        self::assertSame('openai', $parsed->formatKey);
    }

    public function testItParsesAssistantToolCallsWithJsonStringArguments(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [
                ['role' => 'user', 'content' => 'Read the file'],
                [
                    'role' => 'assistant',
                    'content' => null,
                    'tool_calls' => [
                        ['id' => 'call_1', 'type' => 'function', 'function' => ['name' => 'read_file', 'arguments' => '{"path":"/x"}']],
                    ],
                ],
                ['role' => 'tool', 'tool_call_id' => 'call_1', 'content' => 'file-a'],
                ['role' => 'user', 'content' => 'Thanks'],
            ],
        ]));

        $assistant = $parsed->messages[1];
        self::assertInstanceOf(AssistantMessage::class, $assistant);
        self::assertCount(1, $assistant->toolCalls());
        self::assertSame('call_1', $assistant->toolCalls()[0]->toolCallId);
        self::assertSame('read_file', $assistant->toolCalls()[0]->toolName);
        self::assertSame(['path' => '/x'], $assistant->toolCalls()[0]->toolInput);

        $tool = $parsed->messages[2];
        self::assertInstanceOf(ToolMessage::class, $tool);
        self::assertInstanceOf(ToolResultPart::class, $tool->parts[0]);
        self::assertSame('call_1', $tool->parts[0]->toolCallId);
        self::assertSame('file-a', $tool->parts[0]->result);
    }

    public function testItParsesAssistantReasoningContent(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello', 'reasoning_content' => 'thinking...'],
                ['role' => 'user', 'content' => 'And now?'],
            ],
        ]));

        $assistant = $parsed->messages[1];
        self::assertInstanceOf(AssistantMessage::class, $assistant);
        self::assertInstanceOf(ReasoningPart::class, $assistant->parts[1]);
        self::assertSame('thinking...', $assistant->parts[1]->reasoning);
    }

    public function testItParsesUserContentParts(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [[
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'What is this?'],
                    ['type' => 'image_url', 'image_url' => ['url' => 'data:image/png;base64,abc', 'detail' => 'low']],
                ],
            ]],
        ]));

        $user = $parsed->messages[0];
        self::assertCount(2, $user->parts);
        self::assertInstanceOf(ImagePart::class, $user->parts[1]);
        self::assertSame('data:image/png;base64,abc', $user->parts[1]->imageUrl);
        self::assertSame('low', $user->parts[1]->detail);
    }

    public function testItParsesToolsAndChoice(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'tools' => [[
                'type' => 'function',
                'function' => ['name' => 'weather', 'description' => 'Weather.', 'parameters' => ['type' => 'object'], 'strict' => true],
            ]],
            'tool_choice' => ['type' => 'function', 'function' => ['name' => 'weather']],
            'parallel_tool_calls' => false,
        ]));

        self::assertCount(1, $parsed->tools);
        self::assertSame('weather', $parsed->tools[0]->name);
        self::assertSame(['strict' => true], $parsed->tools[0]->metadata);
        self::assertSame(ToolChoiceMode::TOOL, $parsed->toolChoice->mode);
        self::assertSame('weather', $parsed->toolChoice->toolName);
        self::assertTrue($parsed->toolConfig->disableParallel);
    }

    public function testItMapsToolChoiceVocabulary(): void
    {
        $base = ['model' => 'm', 'messages' => [['role' => 'user', 'content' => 'Hi']]];

        self::assertSame(ToolChoiceMode::NONE, $this->sut->parseRequest($this->request([...$base, 'tool_choice' => 'none']))->toolChoice->mode);
        self::assertSame(ToolChoiceMode::ANY, $this->sut->parseRequest($this->request([...$base, 'tool_choice' => 'required']))->toolChoice->mode);
        self::assertSame(ToolChoiceMode::AUTO, $this->sut->parseRequest($this->request([...$base, 'tool_choice' => 'auto']))->toolChoice->mode);
    }

    public function testItParsesGenerationParamsIncludingMaxCompletionTokensAndStopString(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'temperature' => 0.5,
            'max_completion_tokens' => 128,
            'stop' => 'END',
            'seed' => 42,
            'logprobs' => true,
            'top_logprobs' => 3,
        ]));

        $generation = $parsed->generation;
        self::assertSame(0.5, $generation->temperature);
        self::assertSame(128, $generation->maxTokens);
        self::assertSame(['END'], $generation->stopSequences);
        self::assertSame(42, $generation->seed);
        self::assertTrue($generation->logprobs);
        self::assertSame(3, $generation->topLogprobs);
    }

    public function testItPrefersMaxCompletionTokensOverMaxTokens(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'max_tokens' => 64,
            'max_completion_tokens' => 128,
        ]));

        self::assertSame(128, $parsed->generation->maxTokens);
    }

    public function testItParsesResponseFormatJsonSchema(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'response_format' => [
                'type' => 'json_schema',
                'json_schema' => ['name' => 'out', 'schema' => ['type' => 'object'], 'strict' => true],
            ],
        ]));

        self::assertSame(ResponseFormatType::JSON_SCHEMA, $parsed->responseFormat->type);
        self::assertSame(['type' => 'object'], $parsed->responseFormat->jsonSchema);
        self::assertSame('out', $parsed->responseFormat->name);
        self::assertTrue($parsed->responseFormat->strict);
    }

    public function testItParsesStreamOptionsAndParksUnmappableParamsInProviderExtensions(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'stream' => true,
            'stream_options' => ['include_usage' => true],
            'n' => 2,
            'logit_bias' => ['50256' => -100],
            'user' => 'user-1',
        ]));

        self::assertTrue($parsed->stream->enabled);
        self::assertTrue($parsed->stream->includeUsage);
        self::assertSame(['n' => 2, 'logit_bias' => ['50256' => -100], 'user' => 'user-1'], $parsed->providerExtensions);
    }

    public function testItParsesHawkiExtensions(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [['role' => 'user', 'content' => 'Hi']],
            'hawki' => ['tools' => ['capability:web_search:auto'], 'attachments' => ['uuid-1']],
        ]));

        self::assertSame(['capability:web_search:auto'], $parsed->hawkiExtension('tools'));
        self::assertSame(['uuid-1'], $parsed->hawkiExtension('attachments'));
    }

    public function testItRequiresMessages(): void
    {
        try {
            $this->sut->parseRequest($this->request(['model' => 'm']));
            self::fail('Expected InvalidRequestBodyException.');
        } catch (InvalidRequestBodyException $exception) {
            self::assertSame('missing_input', $exception->errorCode());
        }
    }

    public function testItRejectsATrailingAssistantMessage(): void
    {
        $this->expectException(InvalidInputItemException::class);
        self::assertSame('missing_user_turn', $this->tryParseErrorCode([
            'model' => 'm',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello'],
            ],
        ]));
    }

    public function testItRejectsATrailingToolCallAssistantMessage(): void
    {
        $this->expectException(InvalidInputItemException::class);
        $this->tryParseErrorCode([
            'model' => 'm',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                    ['id' => 'c', 'type' => 'function', 'function' => ['name' => 'f', 'arguments' => '{}']],
                ]],
            ],
        ]);
    }

    public function testItAllowsATrailingToolResultForContinuations(): void
    {
        $parsed = $this->sut->parseRequest($this->request([
            'model' => 'm',
            'messages' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => null, 'tool_calls' => [
                    ['id' => 'c', 'type' => 'function', 'function' => ['name' => 'f', 'arguments' => '{}']],
                ]],
                ['role' => 'tool', 'tool_call_id' => 'c', 'content' => 'result'],
            ],
        ]));

        self::assertCount(3, $parsed->messages);
    }

    public function testItFormatsATextResponse(): void
    {
        $response = new AiResponse(
            id: 'inv_1',
            model: 'gpt-4.1-nano',
            created: 1700000000,
            message: AssistantMessage::fromText('Hello!'),
            finishReason: FinishReason::stop(),
            systemFingerprint: null,
        );

        $json = $this->sut->formatResponse($response);

        self::assertSame('chatcmpl-inv_1', $json->getData(true)['id']);
        self::assertSame([
            'id' => 'chatcmpl-inv_1',
            'object' => 'chat.completion',
            'created' => 1700000000,
            'model' => 'gpt-4.1-nano',
            'system_fingerprint' => null,
            'choices' => [[
                'index' => 0,
                'message' => [
                    'role' => 'assistant',
                    'content' => 'Hello!',
                    'reasoning_content' => null,
                    'tool_calls' => null,
                    'refusal' => null,
                ],
                'finish_reason' => 'stop',
            ]],
            'usage' => ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0],
        ], $json->getData(true));
    }

    public function testItFormatsToolCallsWithStringifiedArguments(): void
    {
        $response = new AiResponse(
            id: 'inv_2',
            model: 'm',
            created: 1,
            message: new AssistantMessage(parts: [
                new ToolCallPart(toolCallId: 'call_9', toolName: 'read_file', toolInput: ['path' => '/x']),
            ]),
            finishReason: new FinishReason(FinishReasonType::TOOL_CALLS),
        );

        $choice = $this->sut->formatResponse($response)->getData(true)['choices'][0];

        self::assertSame('tool_calls', $choice['finish_reason']);
        self::assertNull($choice['message']['content']);
        self::assertSame('call_9', $choice['message']['tool_calls'][0]['id']);
        self::assertSame('read_file', $choice['message']['tool_calls'][0]['function']['name']);
        self::assertSame('{"path":"/x"}', $choice['message']['tool_calls'][0]['function']['arguments']);
    }

    public function testItMapsFinishReasonVocabulary(): void
    {
        self::assertSame('stop', OpenAiChatCompletionsFormatter::finishReason(FinishReasonType::STOP));
        self::assertSame('length', OpenAiChatCompletionsFormatter::finishReason(FinishReasonType::LENGTH));
        self::assertSame('tool_calls', OpenAiChatCompletionsFormatter::finishReason(FinishReasonType::TOOL_CALLS));
        self::assertSame('content_filter', OpenAiChatCompletionsFormatter::finishReason(FinishReasonType::REFUSAL));
        self::assertSame('stop', OpenAiChatCompletionsFormatter::finishReason(FinishReasonType::ERROR));
    }

    public function testItFormatsReasoningAndRefusalParts(): void
    {
        $response = new AiResponse(
            id: 'inv_3',
            model: 'm',
            created: 1,
            message: new AssistantMessage(parts: [
                new ReasoningPart(reasoning: 'pondering'),
                new RefusalPart('cannot help'),
                TextPart::from('Sorry.'),
            ]),
            finishReason: FinishReason::stop(),
        );

        $message = $this->sut->formatResponse($response)->getData(true)['choices'][0]['message'];

        self::assertSame('Sorry.', $message['content']);
        self::assertSame('pondering', $message['reasoning_content']);
        self::assertSame('cannot help', $message['refusal']);
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
     * @param array<string, mixed> $body
     */
    private function tryParseErrorCode(array $body): string
    {
        try {
            $this->sut->parseRequest($this->request($body));
        } catch (InvalidInputItemException $exception) {
            self::assertSame('missing_user_turn', $exception->errorCode());

            throw $exception;
        }

        self::fail('Expected InvalidInputItemException.');
    }
}
