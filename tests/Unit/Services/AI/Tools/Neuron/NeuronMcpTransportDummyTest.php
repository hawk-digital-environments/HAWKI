<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron;

use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Neuron\NeuronMcpTransportDummy;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(NeuronMcpTransportDummy::class)]
class NeuronMcpTransportDummyTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(NeuronMcpTransportDummy::class, new NeuronMcpTransportDummy());
    }

    // =========================================================================
    // connect / disconnect
    // =========================================================================

    public function testItConnectsWithoutError(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->connect();
        // No exception = pass
        static::assertTrue(true);
    }

    public function testItDisconnectsWithoutError(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->disconnect();
        static::assertTrue(true);
    }

    // =========================================================================
    // receive – initial state
    // =========================================================================

    public function testItReturnsEmptyArrayBeforeAnySend(): void
    {
        $sut = new NeuronMcpTransportDummy();
        static::assertSame([], $sut->receive());
    }

    // =========================================================================
    // send / receive – initialize
    // =========================================================================

    public function testItHandlesInitializeRequest(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->send(['method' => 'initialize', 'id' => 1]);

        // initialize produces no result body, only the echoed id
        static::assertSame(['id' => 1], $sut->receive());
    }

    public function testItEchoesRequestIdForInitialize(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->send(['method' => 'initialize', 'id' => 99]);

        static::assertSame(99, $sut->receive()['id']);
    }

    // =========================================================================
    // send / receive – tools/list
    // =========================================================================

    public function testItHandlesToolsListRequestWithNoToolSet(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->send(['method' => 'tools/list', 'id' => 2]);

        $response = $sut->receive();
        static::assertSame(2, $response['id']);
        static::assertSame([], $response['result']['tools']);
    }

    public function testItHandlesToolsListRequestWithToolSet(): void
    {
        $config = ['name' => 'my_mcp_tool', 'description' => 'Does something useful'];
        $tool = $this->makeToolWithConfig($config);

        $sut = new NeuronMcpTransportDummy();
        $sut->setTool($tool);
        $sut->send(['method' => 'tools/list', 'id' => 3]);

        $response = $sut->receive();
        static::assertSame(3, $response['id']);
        static::assertSame([$config], $response['result']['tools']);
    }

    public function testItEchoesRequestIdForToolsList(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->send(['method' => 'tools/list', 'id' => 42]);

        static::assertSame(42, $sut->receive()['id']);
    }

    public function testItUsesEmptyArrayWhenToolHasNullMcpConfig(): void
    {
        $tool = $this->makeToolWithConfig(null);

        $sut = new NeuronMcpTransportDummy();
        $sut->setTool($tool);
        $sut->send(['method' => 'tools/list', 'id' => 1]);

        static::assertSame([[]], $sut->receive()['result']['tools']);
    }

    // =========================================================================
    // setTool – replaces previous tool
    // =========================================================================

    public function testItReplacesToolOnSubsequentSetToolCalls(): void
    {
        $firstConfig = ['name' => 'first_tool'];
        $secondConfig = ['name' => 'second_tool'];

        $sut = new NeuronMcpTransportDummy();
        $sut->setTool($this->makeToolWithConfig($firstConfig));
        $sut->setTool($this->makeToolWithConfig($secondConfig));

        $sut->send(['method' => 'tools/list', 'id' => 1]);
        $response = $sut->receive();

        static::assertSame([$secondConfig], $response['result']['tools']);
    }

    // =========================================================================
    // send / receive – response is overwritten on each send
    // =========================================================================

    public function testItOverwritesPreviousResponseOnNewSend(): void
    {
        $sut = new NeuronMcpTransportDummy();
        $sut->send(['method' => 'initialize', 'id' => 1]);
        $sut->send(['method' => 'tools/list', 'id' => 2]);

        $response = $sut->receive();
        static::assertSame(2, $response['id']);
        static::assertArrayHasKey('result', $response);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeToolWithConfig(array|null $config): AiTool&MockObject
    {
        $tool = $this->createMock(AiTool::class);
        $tool->method('__get')->willReturnMap([
            ['mcp_config', $config],
        ]);
        // PHP's ?? operator calls __isset before __get; stub it so non-null configs are treated as set.
        $tool->method('__isset')->willReturnCallback(
            fn(string $key) => $key === 'mcp_config' && $config !== null
        );
        return $tool;
    }
}
