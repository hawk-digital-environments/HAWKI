<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Adapters;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Exceptions\InvalidAgentConfigurationException;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Middleware\LoggingMiddleware;
use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AbstractTextGeneratingAgent::class)]
class AbstractTextGeneratingAgentTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeFlags(bool $hasSamplingParams = true): AiModelFlags&MockObject
    {
        $flags = $this->createMock(AiModelFlags::class);
        $flags->method('hasFeatureSamplingParameters')->willReturn($hasSamplingParams);
        return $flags;
    }

    private function makeModel(bool $hasSamplingParams = true): AiModel&MockObject
    {
        $flags = $this->makeFlags($hasSamplingParams);
        $model = $this->createMock(AiModel::class);
        // AiModel exposes cast properties via Eloquent's magic __get → getAttribute.
        // Mocking __get directly is the same pattern used in UserMessageAttachmentsTest.
        $model->method('__get')->willReturnCallback(
            fn(string $key) => $key === 'flags' ? $flags : null
        );
        return $model;
    }

    private function makeProxy(): AiProviderProxy
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getAdditionalDriverOptions')->willReturn([]);

        return new AiProviderProxy(
            provider: $this->createMock(AiProvider::class),
            adapter: $adapter,
            driver: $this->createMock(Provider::class),
        );
    }

    private function makeContext(
        bool $hasSamplingParams = true,
        ?AiModelParameters $params = null
    ): AgentRequestContext
    {
        return new AgentRequestContext(
            provider: $this->makeProxy(),
            model: $this->makeModel($hasSamplingParams),
            modelParameters: $params ?? new AiModelParameters(['temperature' => 0.7, 'top_p' => 0.9, 'max_tokens' => 1024]),
        );
    }

    /**
     * Creates a concrete ChatAgent using a promptString so no message-extraction logic runs.
     */
    private function makeAgentWithPrompt(
        AgentRequestContext $context,
        string $instructions = 'Be helpful.',
        string $promptString = 'Hello AI',
        array $messages = [],
        iterable $tools = [],
    ): ChatAgent {
        return new ChatAgent(
            context: $context,
            instructions: $instructions,
            messages: $messages,
            tools: $tools,
            promptString: $promptString,
        );
    }

    /**
     * Creates a UserMessage with non-empty content (satisfies last-message validation).
     */
    private function makeUserMessage(string $content = 'User question'): UserMessage
    {
        return new UserMessage(content: $content);
    }

    private function makeAssistantMessage(string $content = 'AI answer'): Message
    {
        return new Message(role: MessageRole::Assistant, content: $content);
    }

    // =========================================================================
    // Construction — via promptString
    // =========================================================================

    public function testItConstructsWithExplicitPromptString(): void
    {
        $sut = $this->makeAgentWithPrompt($this->makeContext());
        static::assertInstanceOf(AbstractTextGeneratingAgent::class, $sut);
    }

    // =========================================================================
    // Construction — via messages array
    // =========================================================================

    public function testItConstructsFromMessagesWhenNoPromptStringGiven(): void
    {
        $context = $this->makeContext();
        $sut = new ChatAgent(
            context: $context,
            instructions: 'Be helpful.',
            messages: [$this->makeAssistantMessage(), $this->makeUserMessage('My question')],
            tools: [],
        );
        static::assertInstanceOf(AbstractTextGeneratingAgent::class, $sut);
    }

    public function testItPopsLastUserMessageAsPromptString(): void
    {
        $context = $this->makeContext();
        $prior = $this->makeAssistantMessage('prior');

        $sut = new ChatAgent(
            context: $context,
            instructions: 'Be helpful.',
            messages: [$prior, $this->makeUserMessage('actual prompt')],
            tools: [],
        );

        // The prior assistant message remains in history; the user message becomes the prompt
        static::assertSame([$prior], $sut->messages());
    }

    // =========================================================================
    // Construction — validation errors
    // =========================================================================

    public function testItThrowsWhenNeitherPromptNorMessagesProvided(): void
    {
        $this->expectException(InvalidAgentConfigurationException::class);
        $this->expectExceptionMessage('Either a promptString or a non-empty messages array must be provided');

        new ChatAgent(
            context: $this->makeContext(),
            instructions: 'Be helpful.',
            messages: [],
            tools: [],
        );
    }

    public function testItThrowsWhenLastMessageIsNotAMessageInstance(): void
    {
        $this->expectException(InvalidAgentConfigurationException::class);
        $this->expectExceptionMessage('instance of');

        // @phpstan-ignore-next-line — intentionally passing wrong type
        new ChatAgent(
            context: $this->makeContext(),
            instructions: 'Be helpful.',
            messages: ['not a message object'],
            tools: [],
        );
    }

    public function testItThrowsWhenLastMessageIsNotUserRole(): void
    {
        $this->expectException(InvalidAgentConfigurationException::class);
        $this->expectExceptionMessage('"user"');

        new ChatAgent(
            context: $this->makeContext(),
            instructions: 'Be helpful.',
            messages: [$this->makeAssistantMessage()],
            tools: [],
        );
    }

    public function testItThrowsWhenLastUserMessageHasEmptyContent(): void
    {
        $this->expectException(InvalidAgentConfigurationException::class);
        $this->expectExceptionMessage('non-empty content');

        new ChatAgent(
            context: $this->makeContext(),
            instructions: 'Be helpful.',
            messages: [new UserMessage(content: '')],
            tools: [],
        );
    }

    // =========================================================================
    // instructions()
    // =========================================================================

    public function testItWrapsInstructionsWithMetaPreamble(): void
    {
        $sut = $this->makeAgentWithPrompt($this->makeContext(), instructions: 'Do the thing.');
        $result = (string)$sut->instructions();

        static::assertStringContainsString('HKI_META', $result);
        static::assertStringContainsString('Do the thing.', $result);
    }

    public function testItInstructionsOutputMatchesMessageMetaBlocksWrapInstructions(): void
    {
        $instructions = 'Custom system prompt.';
        $sut = $this->makeAgentWithPrompt($this->makeContext(), instructions: $instructions);

        static::assertSame(
            MessageMetaBlocks::wrapInstructions($instructions),
            (string)$sut->instructions()
        );
    }

    // =========================================================================
    // getContext()
    // =========================================================================

    public function testItGetContextReturnsInjectedContext(): void
    {
        $context = $this->makeContext();
        $sut = $this->makeAgentWithPrompt($context);
        static::assertSame($context, $sut->getContext());
    }

    // =========================================================================
    // messages()
    // =========================================================================

    public function testItMessagesReturnsEmptyWhenNoHistoryMessages(): void
    {
        $sut = $this->makeAgentWithPrompt($this->makeContext());
        static::assertSame([], $sut->messages());
    }

    public function testItMessagesReturnsHistoryExcludingPoppedPrompt(): void
    {
        $prior = $this->makeAssistantMessage('prior answer');
        $context = $this->makeContext();

        $sut = new ChatAgent(
            context: $context,
            instructions: 'Be helpful.',
            messages: [$prior, $this->makeUserMessage('question')],
            tools: [],
        );

        $messages = $sut->messages();
        static::assertCount(1, $messages);
        static::assertSame($prior, $messages[0]);
    }

    // =========================================================================
    // tools()
    // =========================================================================

    public function testItToolsReturnsEmptyIterableWhenNoToolsProvided(): void
    {
        $sut = $this->makeAgentWithPrompt($this->makeContext(), tools: []);
        static::assertSame([], [...$sut->tools()]);
    }

    // =========================================================================
    // middleware()
    // =========================================================================

    public function testItMiddlewareContainsLoggingMiddleware(): void
    {
        $sut = $this->makeAgentWithPrompt($this->makeContext());
        $middleware = $sut->middleware();

        static::assertCount(1, $middleware);
        static::assertInstanceOf(LoggingMiddleware::class, $middleware[0]);
    }

    // =========================================================================
    // maxTokens / temperature / topP — sampling params enabled
    // =========================================================================

    public function testItMaxTokensReturnsValueFromParametersWhenSamplingEnabled(): void
    {
        $params = new AiModelParameters(['max_tokens' => 512]);
        $context = $this->makeContext(hasSamplingParams: true, params: $params);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertSame(512, $sut->maxTokens());
    }

    public function testItTemperatureReturnsValueFromParametersWhenSamplingEnabled(): void
    {
        $params = new AiModelParameters(['temperature' => 0.42]);
        $context = $this->makeContext(hasSamplingParams: true, params: $params);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertSame(0.42, $sut->temperature());
    }

    public function testItTopPReturnsValueFromParametersWhenSamplingEnabled(): void
    {
        $params = new AiModelParameters(['top_p' => 0.8]);
        $context = $this->makeContext(hasSamplingParams: true, params: $params);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertSame(0.8, $sut->topP());
    }

    // =========================================================================
    // maxTokens / temperature / topP — sampling params disabled
    // =========================================================================

    public function testItMaxTokensReturnsNullWhenSamplingParamsNotSupported(): void
    {
        $context = $this->makeContext(hasSamplingParams: false);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertNull($sut->maxTokens());
    }

    public function testItTemperatureReturnsNullWhenSamplingParamsNotSupported(): void
    {
        $context = $this->makeContext(hasSamplingParams: false);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertNull($sut->temperature());
    }

    public function testItTopPReturnsNullWhenSamplingParamsNotSupported(): void
    {
        $context = $this->makeContext(hasSamplingParams: false);
        $sut = $this->makeAgentWithPrompt($context);

        static::assertNull($sut->topP());
    }

    // =========================================================================
    // attachments extracted from popped UserMessage
    // =========================================================================

    public function testItExtractsAttachmentsFromPoppedUserMessage(): void
    {
        $context = $this->makeContext();

        // UserMessage with no attachments — we just verify construction succeeds and
        // that getAttachments() (protected) does not throw. We test via messages().
        $userMsg = $this->makeUserMessage('the prompt');
        $sut = new ChatAgent(
            context: $context,
            instructions: 'inst',
            messages: [$userMsg],
            tools: [],
        );

        // No prior messages remain after popping
        static::assertSame([], $sut->messages());
    }
}
