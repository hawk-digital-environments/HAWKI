<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Contracts\ToolInterface as HawkiToolInterface;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Neuron\NeuronMcpTransportDummy;
use App\Services\Ai\Tools\Neuron\NeuronToolConverter;
use App\Services\Ai\Values\ToolType;
use App\Utils\Lists\LazySingletonList;
use NeuronAI\MCP\McpException;
use NeuronAI\Tools\ToolInterface as NeuronToolInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;
use Tests\Unit\Services\AI\Tools\Neuron\NeuronToolConverterTestFixtures\ValidHawkiTool;

#[CoversClass(NeuronToolConverter::class)]
class NeuronToolConverterTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private LazySingletonList&MockObject $mcpClientList;
    private NeuronMcpTransportDummy $transportDummy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->mcpClientList = $this->createMock(LazySingletonList::class);
        $this->transportDummy = new NeuronMcpTransportDummy();
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(NeuronToolConverter::class, $this->makeSut());
    }

    // =========================================================================
    // convert – unsupported type
    // =========================================================================

    // AiToolType only has MCP and FUNCTION, so the unsupported-type branch is
    // unreachable with real data. We verify the two supported types dispatch
    // to the correct sub-method instead (tested below).

    // =========================================================================
    // convert – FUNCTION tools
    // =========================================================================

    public function testItThrowsMcpExceptionWhenFunctionToolClassDoesNotExist(): void
    {
        $tool = $this->makeAiTool(ToolType::FUNCTION, className: 'App\\Does\\Not\\Exist');

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('App\\Does\\Not\\Exist');

        $this->makeSut()->convert($tool);
    }

    public function testItThrowsMcpExceptionWhenFunctionToolClassDoesNotImplementInterface(): void
    {
        // Use a real class that exists but does NOT implement HawkiToolInterface
        $tool = $this->makeAiTool(ToolType::FUNCTION, className: \stdClass::class);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage(HawkiToolInterface::class);

        $this->makeSut()->convert($tool);
    }

    public function testItResolvesValidFunctionTool(): void
    {
        $sut = $this->makeSut();
        $sut->setService(ValidHawkiTool::class, new ValidHawkiTool());

        $tool = $this->makeAiTool(ToolType::FUNCTION, className: ValidHawkiTool::class);

        $result = $sut->convert($tool);

        static::assertInstanceOf(NeuronToolInterface::class, $result);
    }

    // =========================================================================
    // convert – MCP tools
    // =========================================================================

    public function testItThrowsMcpExceptionWhenMcpToolHasNoServer(): void
    {
        $tool = $this->makeAiTool(ToolType::MCP, server: null);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('MCP tool is not linked to an MCP server');

        $this->makeSut()->convert($tool);
    }

    public function testItThrowsMcpExceptionWhenMcpToolHasEmptyConfig(): void
    {
        $server = $this->createMock(McpServer::class);
        $tool = $this->makeAiTool(ToolType::MCP, server: $server, mcpConfig: []);

        $this->expectException(McpException::class);
        $this->expectExceptionMessage('MCP tool does not have a config');

        $this->makeSut()->convert($tool);
    }

    public function testItConvertsMcpToolToNeuronTool(): void
    {
        $server = $this->createMock(McpServer::class);
        $mcpClient = $this->createMock(HawkiMcpClient::class);

        $this->mcpClientList->method('get')->willReturn($mcpClient);

        $tool = $this->makeAiTool(
            ToolType::MCP,
            name: 'my_mcp_tool',
            server: $server,
            mcpConfig: [
                'name' => 'my_mcp_tool',
                'description' => 'Test MCP tool',
                'inputSchema' => ['type' => 'object', 'properties' => []],
            ]
        );

        $result = $this->makeSut()->convert($tool);

        static::assertInstanceOf(NeuronToolInterface::class, $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(): NeuronToolConverter
    {
        return new NeuronToolConverter(
            $this->logger,
            $this->mcpClientList,
            $this->transportDummy
        );
    }

    private function makeAiTool(
        ToolType       $type,
        string         $name = 'test_tool',
        string|null    $className = null,
        McpServer|null $server = null,
        array|null     $mcpConfig = null,
    ): AiTool&MockObject
    {
        $tool = $this->createMock(AiTool::class);
        $tool->method('__get')->willReturnMap([
            ['type', $type],
            ['name', $name],
            ['class_name', $className],
            ['server', $server],
            ['mcp_config', $mcpConfig],
        ]);
        // PHP's empty() calls __isset before __get; stub it so the config presence is reflected correctly.
        $tool->method('__isset')->willReturnCallback(
            fn(string $key) => $key === 'mcp_config' && $mcpConfig !== null
        );
        return $tool;
    }
}
