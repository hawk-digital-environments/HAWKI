<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools\LaravelAi;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\LaravelAi\LaravelMcpTool;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Ai\Values\OnlineStatus;
use Laravel\Ai\Tools\Request;
use Mcp\Types\CallToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\TestCase;

#[CoversClass(LaravelMcpTool::class)]
class LaravelMcpToolTest extends TestCase
{
    public function testSettingsOverrideModelArgumentsOnTheMcpCall(): void
    {
        // A schema variant that still advertises dataset_id (e.g. imported
        // from a server that has not hidden it yet): whatever the model
        // fills in, the server-side setting must win.
        $client = $this->clientExpectingCall(
            'query-search',
            ['query' => 'how do students learn', 'dataset_id' => 'assistant_12'],
        );

        $tool = $this->buildTool($client, withDatasetIdProperty: true);
        $tool->setSettings(['dataset_id' => 'assistant_12']);

        $tool->handle(new Request(['query' => 'how do students learn', 'dataset_id' => 'hawki-v1'], 'fc_1'));
    }

    public function testSettingsInjectArgumentsTheModelDidNotProvide(): void
    {
        $client = $this->clientExpectingCall(
            'query-search',
            ['query' => 'how do students learn', 'dataset_id' => 'assistant_12'],
        );

        $tool = $this->buildTool($client);
        $tool->setSettings(['dataset_id' => 'assistant_12']);

        $tool->handle(new Request(['query' => 'how do students learn'], 'fc_2'));
    }

    public function testArgumentsPassThroughUntouchedWithoutSettings(): void
    {
        $client = $this->clientExpectingCall(
            'query-search',
            ['query' => 'how do students learn', 'top_k' => 6],
        );

        $tool = $this->buildTool($client);

        $tool->handle(new Request(['query' => 'how do students learn', 'top_k' => 6], 'fc_3'));
    }

    public function testModelCannotSmuggleUndeclaredArgumentsPastSchemaValidation(): void
    {
        // The tool schema is closed: a hallucinated dataset_id (not part of
        // the advertised schema) never reaches the MCP server. The model
        // receives an error and retries with declared arguments only —
        // at which point the injected setting scopes the call.
        $client = $this->createMock(HawkiMcpClient::class);
        $client->expects(static::never())->method('callTool');

        $tool = $this->buildTool($client);
        $tool->setSettings(['dataset_id' => 'assistant_12']);

        $result = $tool->handle(new Request(['query' => 'how do students learn', 'dataset_id' => 'hawki-v1'], 'fc_4'));

        static::assertStringContainsString('[ERROR]', $result);
        static::assertStringContainsString('invalid arguments', $result);
    }

    private function buildTool(HawkiMcpClient $client, bool $withDatasetIdProperty = false): LaravelMcpTool
    {
        $properties = [
            'query' => ['type' => 'string'],
            'top_k' => ['type' => 'integer'],
        ];

        if ($withDatasetIdProperty) {
            $properties['dataset_id'] = ['type' => 'string'];
        }

        $aiTool = new AiTool([
            'name' => 'hawki-rag-query-search',
            'mcp_name' => 'query-search',
            'description' => 'Search the knowledge base.',
            'type' => ToolType::MCP,
            'mcp_config' => [
                'name' => 'query-search',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => $properties,
                    'required' => ['query'],
                ],
            ],
        ]);

        $server = new McpServer([
            'url' => 'http://rag.test/mcp',
            'server_label' => 'hawki-rag',
            'status' => OnlineStatus::ONLINE->value,
        ]);

        return new LaravelMcpTool(
            logger: new NullLogger(),
            tool: $aiTool->setRelation('server', $server),
            client: $client,
        );
    }

    private function clientExpectingCall(string $mcpName, array $expectedArguments): HawkiMcpClient&MockObject
    {
        $client = $this->createMock(HawkiMcpClient::class);

        $client
            ->expects(static::once())
            ->method('callTool')
            ->with(
                static::identicalTo($mcpName),
                static::callback(static function (array $arguments) use ($expectedArguments): bool {
                    ksort($arguments);
                    ksort($expectedArguments);

                    return $arguments === $expectedArguments;
                }),
            )
            ->willReturn(new CallToolResult(content: []));

        return $client;
    }
}
