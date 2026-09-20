<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Formatters\Implementations\OpenResponses;

use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\FinishReasonType;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\ToolMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\ToolCallPart;
use App\Services\Ai\Chat\Values\Parts\ToolResultPart;
use App\Services\Ai\Chat\Values\Tools\ToolChoiceMode;
use App\Services\Ai\Chat\Values\UsageInfo;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Exceptions\InvalidRequestBodyException;
use App\Services\Ai\Formatters\Exceptions\UnsupportedStatefulParameterException;
use App\Services\Ai\Formatters\Implementations\OpenResponses\OpenResponsesFormatter;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(OpenResponsesFormatter::class)]
class OpenResponsesFormatterTest extends TestCase
{
    private OpenResponsesFormatter $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new OpenResponsesFormatter();
    }

    public function testItConstructs(): void
    {
        self::assertInstanceOf(OpenResponsesFormatter::class, $this->sut);
    }

    public function testItParsesStringInputIntoUserMessage(): void
    {
        $request = $this->request(['input' => 'Hello', 'model' => 'gpt-4o']);

        $result = $this->sut->parseRequest($request);

        self::assertSame('gpt-4o', $result->model);
        self::assertCount(1, $result->messages);
        self::assertInstanceOf(UserMessage::class, $result->messages[0]);
        self::assertSame('Hello', $result->messages[0]->text());
    }

    public function testItParsesInstructionsIntoSystemInstruction(): void
    {
        $request = $this->request(['input' => 'Hi', 'instructions' => 'Be terse.']);

        $result = $this->sut->parseRequest($request);

        self::assertSame('Be terse.', $result->systemInstructionText());
    }

    public function testItParsesSystemRoleItemsIntoSystemInstruction(): void
    {
        $request = $this->request([
            'input' => [
                ['type' => 'message', 'role' => 'system', 'content' => 'Be kind.'],
                ['type' => 'message', 'role' => 'user', 'content' => 'Hi'],
            ],
        ]);

        $result = $this->sut->parseRequest($request);

        self::assertSame('Be kind.', $result->systemInstructionText());
    }

    public function testItParsesMultimodalContentParts(): void
    {
        $request = $this->request([
            'input' => [
                [
                    'type' => 'message',
                    'role' => 'user',
                    'content' => [
                        ['type' => 'input_text', 'text' => 'What is this?'],
                        ['type' => 'input_image', 'image_url' => 'https://example.com/cat.png', 'detail' => 'high'],
                    ],
                ],
            ],
        ]);

        $result = $this->sut->parseRequest($request);

        $parts = $result->messages[0]->parts;
        self::assertCount(2, $parts);
        self::assertInstanceOf(\App\Services\Ai\Chat\Values\Parts\TextPart::class, $parts[0]);
        self::assertInstanceOf(\App\Services\Ai\Chat\Values\Parts\ImagePart::class, $parts[1]);
        self::assertSame('https://example.com/cat.png', $parts[1]->imageUrl);
        self::assertSame('high', $parts[1]->detail);
    }

    public function testItParsesToolLoopHistoryItems(): void
    {
        $request = $this->request([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Search for cats'],
                ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'web_search', 'arguments' => '{"q":"cats"}'],
                ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'Cats are great.'],
                ['type' => 'message', 'role' => 'user', 'content' => 'Thanks!'],
            ],
        ]);

        $result = $this->sut->parseRequest($request);

        self::assertCount(4, $result->messages);
        self::assertInstanceOf(AssistantMessage::class, $result->messages[1]);
        self::assertInstanceOf(ToolCallPart::class, $result->messages[1]->parts[0]);
        self::assertSame('call_1', $result->messages[1]->parts[0]->toolCallId);
        self::assertSame(['q' => 'cats'], $result->messages[1]->parts[0]->toolInput);

        self::assertInstanceOf(ToolMessage::class, $result->messages[2]);
        self::assertInstanceOf(ToolResultPart::class, $result->messages[2]->parts[0]);
        self::assertSame('Cats are great.', $result->messages[2]->parts[0]->result);
    }

    public function testItRejectsStoreTrue(): void
    {
        $this->expectException(UnsupportedStatefulParameterException::class);
        $this->expectExceptionMessage('stateless proxy');

        $this->sut->parseRequest($this->request(['input' => 'Hi', 'store' => true]));
    }

    public function testItAcceptsStoreFalse(): void
    {
        $result = $this->sut->parseRequest($this->request(['input' => 'Hi', 'store' => false]));

        self::assertCount(1, $result->messages);
    }

    public function testItRejectsPreviousResponseId(): void
    {
        $this->expectException(UnsupportedStatefulParameterException::class);

        $this->sut->parseRequest($this->request(['input' => 'Hi', 'previous_response_id' => 'resp_123']));
    }

    public function testItRejectsMissingInput(): void
    {
        $this->expectException(InvalidRequestBodyException::class);

        $this->sut->parseRequest($this->request(['model' => 'gpt-4o']));
    }

    public function testItRejectsUnknownItemTypes(): void
    {
        $this->expectException(InvalidInputItemException::class);
        $this->expectExceptionMessage('teleport_call');

        $this->sut->parseRequest($this->request([
            'input' => [['type' => 'teleport_call', 'call_id' => 'x']],
        ]));
    }

    public function testItRejectsNonContinuableTrailingItem(): void
    {
        $this->expectException(InvalidInputItemException::class);
        $this->expectExceptionMessage('must end with a user message or a function_call_output');

        $this->sut->parseRequest($this->request([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hi'],
                ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'read_file', 'arguments' => '{}'],
            ],
        ]));
    }

    public function testItAcceptsTrailingFunctionCallOutputAsContinuationTurn(): void
    {
        $result = $this->sut->parseRequest($this->request([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'List the files'],
                ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'read_file', 'arguments' => '{"path":"/tmp"}'],
                ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'file-a\nfile-b'],
            ],
        ]));

        static::assertCount(3, $result->messages);
        static::assertInstanceOf(ToolMessage::class, $result->messages[2]);
        static::assertSame('call_1', $result->messages[2]->parts[0]->toolCallId);
        static::assertSame('file-a\nfile-b', $result->messages[2]->parts[0]->result);
    }

    public function testItIgnoresTrailingReasoningItemsForTheContinuationCheck(): void
    {
        $result = $this->sut->parseRequest($this->request([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hi'],
                ['type' => 'function_call', 'call_id' => 'call_1', 'name' => 'read_file', 'arguments' => '{}'],
                ['type' => 'function_call_output', 'call_id' => 'call_1', 'output' => 'ok'],
                ['type' => 'reasoning', 'summary' => [['type' => 'summary_text', 'text' => 'hm']]],
            ],
        ]));

        static::assertCount(4, $result->messages);
    }

    public function testItParsesToolsToolChoiceAndHawkiExtensions(): void
    {
        $request = $this->request([
            'input' => 'Hi',
            'tools' => [
                ['type' => 'function', 'name' => 'web_search', 'description' => 'Search', 'parameters' => ['type' => 'object']],
            ],
            'tool_choice' => 'required',
            'temperature' => 0.5,
            'max_output_tokens' => 1000,
            'reasoning' => ['effort' => 'high'],
            'stream' => true,
            'hawki' => [
                'tools' => ['capability:web_search:auto'],
                'attachments' => ['uuid-1'],
                'params' => ['temp' => 0.1],
            ],
        ]);

        $result = $this->sut->parseRequest($request);

        self::assertCount(1, $result->tools);
        self::assertSame('web_search', $result->tools[0]->name);

        self::assertSame(ToolChoiceMode::ANY, $result->toolChoice?->mode);

        self::assertSame(0.5, $result->generation?->temperature);
        self::assertSame(1000, $result->generation?->maxTokens);
        self::assertSame(\App\Services\Ai\Chat\Values\Configs\ReasoningEffort::HIGH, $result->reasoning?->effort);

        self::assertTrue($result->wantsStreaming());

        self::assertSame(['capability:web_search:auto'], $result->hawkiExtension(AiRequest::HAWKI_EXTENSION_TOOLS));
        self::assertSame(['uuid-1'], $result->hawkiExtension(AiRequest::HAWKI_EXTENSION_ATTACHMENTS));
        self::assertSame(['temp' => 0.1], $result->hawkiExtension(AiRequest::HAWKI_EXTENSION_PARAMS));

        self::assertSame('openResponses', $result->formatKey);
    }

    public function testItRoundTripsAParsedRequestThroughFormatResponse(): void
    {
        $result = $this->sut->parseRequest($this->request([
            'input' => [
                ['type' => 'message', 'role' => 'user', 'content' => 'Hi'],
                ['type' => 'message', 'role' => 'assistant', 'content' => 'Hello!'],
                ['type' => 'message', 'role' => 'user', 'content' => 'Bye'],
            ],
            'model' => 'gpt-4o',
        ]));

        self::assertCount(3, $result->messages);
        self::assertSame('Hello!', $result->messages[1]->text());
    }

    public function testItFormatsANonStreamingResponseWithTheFullResourceSkeleton(): void
    {
        $response = new AiResponse(
            id: 'resp_1',
            model: 'gpt-4o',
            created: 1700000000,
            message: AssistantMessage::fromText('Hello world'),
            finishReason: FinishReason::stop(),
            usage: new UsageInfo(promptTokens: 2, completionTokens: 3, totalTokens: 5),
        );

        $result = $this->sut->formatResponse($response);
        $data = json_decode($result->getContent(), true);

        foreach ([
            'id', 'object', 'created_at', 'completed_at', 'status', 'incomplete_details', 'error',
            'model', 'previous_response_id', 'instructions', 'output', 'tools', 'tool_choice',
            'truncation', 'parallel_tool_calls', 'text', 'temperature', 'top_p', 'presence_penalty',
            'frequency_penalty', 'top_logprobs', 'reasoning', 'usage', 'max_output_tokens',
            'max_tool_calls', 'store', 'background', 'service_tier', 'metadata',
            'safety_identifier', 'prompt_cache_key',
        ] as $field) {
            self::assertArrayHasKey($field, $data, "ResponseResource field '{$field}' must always be present.");
        }

        self::assertSame('response', $data['object']);
        self::assertSame('completed', $data['status']);
        self::assertSame('resp_1', $data['id']);
        self::assertSame(2, $data['usage']['input_tokens']);
        self::assertSame(3, $data['usage']['output_tokens']);

        self::assertCount(1, $data['output']);
        self::assertSame('message', $data['output'][0]['type']);
        self::assertSame('Hello world', $data['output'][0]['content'][0]['text']);
    }

    public function testItFormatsToolCallOutputItems(): void
    {
        $response = new AiResponse(
            id: 'resp_1',
            model: 'gpt-4o',
            created: 1700000000,
            message: new AssistantMessage(parts: [
                new ToolCallPart(toolCallId: 'call_1', toolName: 'web_search', toolInput: ['q' => 'cats']),
            ]),
            finishReason: new FinishReason(FinishReasonType::TOOL_CALLS),
        );

        $data = json_decode($this->sut->formatResponse($response)->getContent(), true);

        self::assertSame('function_call', $data['output'][0]['type']);
        self::assertSame('call_1', $data['output'][0]['call_id']);
        self::assertSame('{"q":"cats"}', $data['output'][0]['arguments']);
    }

    public function testItFormatsIncompleteForLengthFinish(): void
    {
        $response = new AiResponse(
            id: 'resp_1',
            model: 'gpt-4o',
            created: 1700000000,
            message: AssistantMessage::fromText('partial'),
            finishReason: new FinishReason(FinishReasonType::LENGTH),
        );

        $data = json_decode($this->sut->formatResponse($response)->getContent(), true);

        self::assertSame('incomplete', $data['status']);
        self::assertSame('max_output_tokens', $data['incomplete_details']['reason']);
    }

    public function testItFormatsErrorsInTheOpenResponsesErrorShape(): void
    {
        $exception = UnsupportedStatefulParameterException::forStore();

        $response = $this->sut->formatError($exception);
        $data = json_decode($response->getContent(), true);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame('store_not_supported', $data['error']['code']);
        self::assertSame('invalid_request_error', $data['error']['type']);
        self::assertArrayHasKey('message', $data['error']);
    }

    public function testItExposesStreamHeaders(): void
    {
        $headers = $this->sut->getStreamHeaders();

        self::assertSame('text/event-stream', $headers['Content-Type']);
        self::assertSame('no-cache', $headers['Cache-Control']);
    }

    private function request(array $body): Request
    {
        return Request::create(
            uri: '/api/hawki/v1/chat',
            method: 'POST',
            parameters: [],
            cookies: [],
            files: [],
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode($body),
        );
    }
}
