<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Assistant\Agents;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgentFromLegacyRequestFactory;
use App\Services\Assistant\Agents\AssistantChatAgentFactory;
use App\Services\Assistant\AssistantRunComposer;
use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Assistant\Values\ComposedAssistantRun;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(AssistantChatAgentFactory::class)]
class AssistantChatAgentFactoryTest extends TestCase
{
    private ChatAgentFromLegacyRequestFactory&MockObject $chatFactory;
    private AssistantRepository&MockObject $assistantRepository;
    private AssistantRunComposer&MockObject $runComposer;
    private Guard&MockObject $guard;
    private LoggerInterface&MockObject $logger;
    private AssistantChatAgentFactory $sut;

    private User $actor;
    private ?User $guardUser;
    private AgentInterface $delegatedAgent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chatFactory = $this->createMock(ChatAgentFromLegacyRequestFactory::class);
        $this->assistantRepository = $this->createMock(AssistantRepository::class);
        $this->runComposer = $this->createMock(AssistantRunComposer::class);
        $this->guard = $this->createMock(Guard::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $auth = $this->createMock(AuthFactory::class);
        $auth->method('guard')->willReturn($this->guard);

        $this->actor = new User();
        $this->actor->id = 42;
        $this->guardUser = $this->actor;
        $this->guard->method('user')->willReturnCallback(fn (): ?User => $this->guardUser);

        $this->delegatedAgent = $this->createStub(AgentInterface::class);

        $this->sut = new AssistantChatAgentFactory(
            $this->chatFactory,
            $this->assistantRepository,
            $this->runComposer,
            $auth,
            $this->logger,
        );
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * @param list<array<string, mixed>> $messages
     */
    private function makeRequest(array $messages, array $extraPayload = []): array
    {
        return [
            'payload' => [
                'model' => 'user-model',
                'messages' => $messages,
                ...$extraPayload,
            ],
        ];
    }

    private function makeAssistant(array $attributes = []): Assistant
    {
        return new Assistant(array_merge([
            'handle' => 'math-tutor',
            'model' => 'assistant-model',
            'allow_model_select' => false,
        ], $attributes));
    }

    /**
     * Configures the repository/composer collaborators for a successfully
     * resolved, visible assistant with a composed run.
     */
    private function givenVisibleAssistant(?Assistant $assistant = null, ?ComposedAssistantRun $run = null): Assistant
    {
        $assistant ??= $this->makeAssistant();

        $this->assistantRepository
            ->method('findOneByHandle')
            ->willReturnCallback(static fn (string $handle): ?Assistant => 'math-tutor' === $handle ? $assistant : null);
        $this->assistantRepository
            ->method('isVisibleTo')
            ->willReturnCallback(fn (Assistant $candidate, User $user): bool => $candidate === $assistant && $user === $this->actor);
        $this->runComposer
            ->method('compose')
            ->willReturn($run ?? new ComposedAssistantRun(
                systemPrompt: 'Composed assistant prompt.',
                modelId: $assistant->model,
                allowModelSelect: $assistant->allow_model_select,
                params: ['temp' => 0.3],
                toolTransferStrings: ['assistant_tool'],
            ));

        return $assistant;
    }

    /**
     * Captures the delegated request for the mocked chat factory and returns
     * the agent stub. The captured requests are appended to the returned
     * ArrayObject, which the test reads after the delegation.
     */
    private function expectDelegation(): \ArrayObject
    {
        $captured = new \ArrayObject();

        $this->chatFactory
            ->expects(static::once())
            ->method('createAgent')
            ->with(static::callback(static function (mixed $request) use ($captured): bool {
                $captured[] = $request;

                return true;
            }))
            ->willReturn($this->delegatedAgent);

        return $captured;
    }

    // =========================================================================
    // Construction & declining
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(AssistantChatAgentFactory::class, $this->sut);
    }

    public function testItReturnsNullForNonLegacyRequests(): void
    {
        $this->chatFactory->expects(static::never())->method('createAgent');

        static::assertNull($this->sut->createAgent('nonsense'));
        static::assertNull($this->sut->createAgent(['payload' => ['messages' => []]]));
    }

    public function testItReturnsNullWithoutAnExplicitAssistantHandle(): void
    {
        $this->assistantRepository->expects(static::never())->method('findOneByHandle');
        $this->chatFactory->expects(static::never())->method('createAgent');

        static::assertNull($this->sut->createAgent($this->makeRequest([
            ['role' => 'system', 'content' => ['text' => 'Conversation prompt.']],
            ['role' => 'user', 'content' => ['text' => '@math-tutor hello']],
        ])));
    }

    public function testItReturnsNullWhenNoUserIsResolved(): void
    {
        $this->guardUser = null;
        $this->chatFactory->expects(static::never())->method('createAgent');

        static::assertNull($this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        )));
    }

    public function testItReturnsNullWhenTheAssistantIsNotVisible(): void
    {
        $assistant = $this->makeAssistant();

        $this->assistantRepository
            ->method('findOneByHandle')
            ->willReturn($assistant);
        $this->assistantRepository
            ->method('isVisibleTo')
            ->willReturn(false);
        $this->logger
            ->expects(static::once())
            ->method('warning');
        $this->chatFactory->expects(static::never())->method('createAgent');

        static::assertNull($this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        )));
    }

    public function testItReturnsNullWhenTheExplicitHandleCannotBeResolved(): void
    {
        $this->assistantRepository
            ->method('findOneByHandle')
            ->willReturn(null);
        $this->chatFactory->expects(static::never())->method('createAgent');

        static::assertNull($this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        )));
    }

    // =========================================================================
    // Assembly of the assistant run
    // =========================================================================

    public function testItRewritesThePayloadFromTheAssistant(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $agent = $this->sut->createAgent($this->makeRequest(
            [
                ['role' => 'system', 'content' => ['text' => 'Old prompt.']],
                ['role' => 'user', 'content' => ['text' => '@math-tutor please help']],
            ],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor'], 'params' => [], 'tools' => []],
        ));

        static::assertSame($this->delegatedAgent, $agent);

        $payload = $captured[0]['payload'];
        static::assertSame('assistant-model', $payload['model']);
        static::assertSame('Composed assistant prompt.', $payload['messages'][0]['content']['text']);
        static::assertSame('please help', $payload['messages'][1]['content']['text']);
        static::assertSame(['temp' => 0.3], $payload['params']);
        static::assertSame(['assistant_tool'], $payload['tools']);
        static::assertArrayNotHasKey('hawkiExtensions', $payload);
    }

    public function testItKeepsTheClientModelWhenTheAssistantAllowsSelection(): void
    {
        $this->givenVisibleAssistant($this->makeAssistant(['allow_model_select' => true]));
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        ));

        static::assertSame('user-model', $captured[0]['payload']['model']);
    }

    public function testItElidesOwnHistoryAttributionsSoNoAnswerSourceBlocksRemain(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [
                ['role' => 'user', 'content' => ['text' => 'Question']],
                ['role' => 'assistant', 'content' => ['text' => 'Own earlier answer'], 'hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
                ['role' => 'user', 'content' => ['text' => 'Follow-up']],
            ],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        ));

        $messages = $captured[0]['payload']['messages'];

        // The assistant's own earlier answer carries no attribution — the
        // chat factory registers it without an ANSWER_SOURCE meta block, so
        // weak models have nothing to echo.
        static::assertSame('Own earlier answer', $messages[2]['content']['text']);
        static::assertArrayNotHasKey('hawkiExtensions', $messages[2]);
        // No foreign attributions remain: the system prompt stays clean.
        static::assertSame('Composed assistant prompt.', $messages[0]['content']['text']);
    }

    public function testItKeepsForeignHistoryAttributionsWithoutExtraPromptRules(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [
                ['role' => 'user', 'content' => ['text' => 'Question']],
                ['role' => 'assistant', 'content' => ['text' => 'Foreign earlier answer'], 'hawkiExtensions' => ['assistant_handle' => 'other-assistant']],
                ['role' => 'user', 'content' => ['text' => 'Follow-up']],
            ],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        ));

        $messages = $captured[0]['payload']['messages'];

        // A genuinely mixed history keeps its disambiguation …
        static::assertSame('other-assistant', $messages[2]['hawkiExtensions']['assistant_handle']);
        // … and relies on the system-wide MessageMetaBlocks preamble: the
        // composed prompt carries no extra meta-block rules of its own.
        static::assertSame('Composed assistant prompt.', $messages[0]['content']['text']);
    }

    public function testItMergesPayloadParamsAndToolsWithTheAssistantRun(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            [
                'hawkiExtensions' => ['assistant_handle' => 'math-tutor'],
                'params' => ['max_thinking_tokens' => 512],
                'tools' => ['capability:web_search:auto'],
            ],
        ));

        $payload = $captured[0]['payload'];
        static::assertSame(['max_thinking_tokens' => 512, 'temp' => 0.3], $payload['params']);
        static::assertSame(['assistant_tool', 'capability:web_search:auto'], $payload['tools']);
    }

    public function testItPrependsASystemMessageWhenThePayloadHasNone(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [['role' => 'user', 'content' => ['text' => 'Hello']]],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        ));

        $messages = $captured[0]['payload']['messages'];
        static::assertSame('system', $messages[0]['role']);
        static::assertSame('Composed assistant prompt.', $messages[0]['content']['text']);
        static::assertSame('user', $messages[1]['role']);
    }

    public function testItStripsTheHandleTokenFromEveryUserMessage(): void
    {
        $this->givenVisibleAssistant();
        $captured = $this->expectDelegation();

        $this->sut->createAgent($this->makeRequest(
            [
                ['role' => 'user', 'content' => ['text' => '@math-tutor first question']],
                ['role' => 'assistant', 'content' => ['text' => '@math-tutor stays untouched']],
                ['role' => 'user', 'content' => ['text' => 'mid @math-tutor second question']],
            ],
            ['hawkiExtensions' => ['assistant_handle' => 'math-tutor']],
        ));

        $messages = $captured[0]['payload']['messages'];
        // The system message is prepended, shifting the original messages by one.
        static::assertSame('first question', $messages[1]['content']['text']);
        static::assertSame('@math-tutor stays untouched', $messages[2]['content']['text']);
        static::assertSame('mid second question', $messages[3]['content']['text']);
    }
}
