<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron\Events;

use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Neuron\Events\BeforeCallingMcpToolFilterEvent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(BeforeCallingMcpToolFilterEvent::class)]
class BeforeCallingMcpToolFilterEventTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $event = new BeforeCallingMcpToolFilterEvent(
            null,
            [],
            $this->makeAiTool(),
            $this->makeMcpClient()
        );

        static::assertInstanceOf(BeforeCallingMcpToolFilterEvent::class, $event);
    }

    // =========================================================================
    // getResult / setResult
    // =========================================================================

    public function testItReturnsNullResultByDefault(): void
    {
        $event = new BeforeCallingMcpToolFilterEvent(null, [], $this->makeAiTool(), $this->makeMcpClient());

        static::assertNull($event->getResult());
    }

    public function testItReturnsInitialResultWhenProvided(): void
    {
        $event = new BeforeCallingMcpToolFilterEvent('{"cached":true}', [], $this->makeAiTool(), $this->makeMcpClient());

        static::assertSame('{"cached":true}', $event->getResult());
    }

    public function testItAllowsSettingResultToShortCircuitMcpCall(): void
    {
        $event = new BeforeCallingMcpToolFilterEvent(null, [], $this->makeAiTool(), $this->makeMcpClient());
        $event->setResult('{"mock":"response"}');

        static::assertSame('{"mock":"response"}', $event->getResult());
    }

    public function testItOverwritesPreviousResult(): void
    {
        $event = new BeforeCallingMcpToolFilterEvent('first', [], $this->makeAiTool(), $this->makeMcpClient());
        $event->setResult('second');

        static::assertSame('second', $event->getResult());
    }

    // =========================================================================
    // Read-only context getters
    // =========================================================================

    public function testItExposesArguments(): void
    {
        $args = ['location' => 'Hamburg', 'unit' => 'celsius'];
        $event = new BeforeCallingMcpToolFilterEvent(null, $args, $this->makeAiTool(), $this->makeMcpClient());

        static::assertSame($args, $event->getArguments());
    }

    public function testItExposesTool(): void
    {
        $tool = $this->makeAiTool();
        $event = new BeforeCallingMcpToolFilterEvent(null, [], $tool, $this->makeMcpClient());

        static::assertSame($tool, $event->getTool());
    }

    public function testItExposesMcpClient(): void
    {
        $client = $this->makeMcpClient();
        $event = new BeforeCallingMcpToolFilterEvent(null, [], $this->makeAiTool(), $client);

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
