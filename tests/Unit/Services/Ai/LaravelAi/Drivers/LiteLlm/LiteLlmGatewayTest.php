<?php
declare(strict_types=1);


namespace Tests\Unit\Services\Ai\LaravelAi\Drivers\LiteLlm;

use App\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGateway;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Ai\Messages\AssistantMessage;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Responses\Data\ToolCall;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGatewayTest\LiteLlmGatewayTestFixtures\ExposedGateway;

#[CoversClass(LiteLlmGateway::class)]
class LiteLlmGatewayTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeGateway(): ExposedGateway
    {
        return new ExposedGateway($this->createMock(Dispatcher::class));
    }

    private function mapConversation(array $messages, ?string $instructions = null): array
    {
        $provider = $this->createMock(Provider::class);
        $provider->method('name')->willReturn('openai');

        return $this->makeGateway()->exposeMapMessagesToInput($messages, $instructions, $provider);
    }

    // =========================================================================
    // Assistant history items
    // =========================================================================

    public function testItEmitsAssistantHistoryAsTypedOutputMessageItem(): void
    {
        $input = $this->mapConversation([
            new UserMessage('Hallo'),
            new AssistantMessage('Hallo! Wie kann ich helfen?'),
            new UserMessage('Erzähl mir etwas'),
        ]);

        static::assertCount(3, $input);

        $assistant = $input[1];
        static::assertSame('message', $assistant['type']);
        static::assertSame('assistant', $assistant['role']);
        static::assertSame('completed', $assistant['status']);
        static::assertStringStartsWith('msg_', $assistant['id']);
        static::assertSame(
            [['type' => 'output_text', 'text' => 'Hallo! Wie kann ich helfen?', 'annotations' => []]],
            $assistant['content']
        );
    }

    public function testItGeneratesDistinctIdsForEachAssistantItem(): void
    {
        $input = $this->mapConversation([
            new UserMessage('a'),
            new AssistantMessage('b'),
            new UserMessage('c'),
            new AssistantMessage('d'),
        ]);

        static::assertNotSame($input[1]['id'], $input[3]['id']);
    }

    public function testItLeavesUserAndSystemItemsUntouched(): void
    {
        $input = $this->mapConversation([new UserMessage('Hallo')], 'Be terse.');

        static::assertSame(['role' => 'system', 'content' => 'Be terse.'], $input[0]);
        static::assertSame(
            ['role' => 'user', 'content' => [['type' => 'input_text', 'text' => 'Hallo']]],
            $input[1]
        );
    }

    public function testItLeavesFunctionCallItemsUntouched(): void
    {
        $toolCall = new ToolCall('fc_1', 'lookup', ['q' => 'x'], 'call_1');

        $input = $this->mapConversation([
            new AssistantMessage('', new Collection([$toolCall])),
        ]);

        static::assertCount(1, $input);
        static::assertSame('function_call', $input[0]['type']);
        static::assertSame('call_1', $input[0]['call_id']);
    }

    public function testItSkipsEmptyAssistantContent(): void
    {
        $input = $this->mapConversation([new AssistantMessage('')]);

        static::assertSame([], $input);
    }
}
