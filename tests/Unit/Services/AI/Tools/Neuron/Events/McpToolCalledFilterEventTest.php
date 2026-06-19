<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron\Events;

use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Neuron\Events\McpToolCalledFilterEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(McpToolCalledFilterEvent::class)]
class McpToolCalledFilterEventTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $event = new McpToolCalledFilterEvent(
            '{"result":"ok"}',
            [],
            $this->makeAiTool(),
            $this->makeMcpClient()
        );

        static::assertInstanceOf(McpToolCalledFilterEvent::class, $event);
    }

    // =========================================================================
    // getResult / setResult
    // =========================================================================

    public function testItReturnsInitialResult(): void
    {
        $event = new McpToolCalledFilterEvent('{"result":"ok"}', [], $this->makeAiTool(), $this->makeMcpClient());

        static::assertSame('{"result":"ok"}', $event->getResult());
    }

    public function testItAllowsReplacingResult(): void
    {
        $event = new McpToolCalledFilterEvent('original', [], $this->makeAiTool(), $this->makeMcpClient());
        $event->setResult('replaced');

        static::assertSame('replaced', $event->getResult());
    }

    public function testItOverwritesPreviousResult(): void
    {
        $event = new McpToolCalledFilterEvent('first', [], $this->makeAiTool(), $this->makeMcpClient());
        $event->setResult('second');
        $event->setResult('third');

        static::assertSame('third', $event->getResult());
    }

    // =========================================================================
    // Read-only context getters
    // =========================================================================

    public function testItExposesArguments(): void
    {
        $args = ['query' => 'latest news'];
        $event = new McpToolCalledFilterEvent('{}', $args, $this->makeAiTool(), $this->makeMcpClient());

        static::assertSame($args, $event->getArguments());
    }

    public function testItExposesTool(): void
    {
        $tool = $this->makeAiTool();
        $event = new McpToolCalledFilterEvent('{}', [], $tool, $this->makeMcpClient());

        static::assertSame($tool, $event->getTool());
    }

    public function testItExposesMcpClient(): void
    {
        $client = $this->makeMcpClient();
        $event = new McpToolCalledFilterEvent('{}', [], $this->makeAiTool(), $client);

        static::assertSame($client, $event->getMcpClient());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAiTool(): AiTool&MockObject
    {
        return $this->createMock(AiTool::class);
    }

    private function makeMcpClient(): HawkiMcpClient&MockObject
    {
        return $this->createMock(HawkiMcpClient::class);
    }
}
