<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Services\Ai\Tools\Values\McpServerTimeouts;
use App\Services\Ai\Tools\Values\McpServerType;
use App\Services\Ai\Tools\Values\McpToolDefinition;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\CoversNothing;
use Tests\TestCase;

#[CoversNothing()]
class AiToolDiscoveryTest extends TestCase
{
    use DatabaseTransactions;

    public function testDiscoveryPreservesAdminEditsWhileUpdatingDefinitionAndServerRename(): void
    {
        $server = McpServer::create([
            'url' => 'https://example.test/discovery',
            'server_label' => 'Original server',
            'api_key' => 'test-key',
            'timeouts' => new McpServerTimeouts(),
            'type' => McpServerType::HTTP,
        ]);
        $repository = app(AiToolRepository::class);
        $first = $repository->upsertMcp(new McpToolDefinition(
            'search',
            'Discovered description',
            ['inputSchema' => ['type' => 'object']],
            'first_capability',
        ), $server);

        self::assertTrue($first->active);
        self::assertSame('Discovered description', $first->description);

        $first->forceFill([
            'admin_managed' => true,
            'active' => false,
            'description' => 'Administrator description',
        ])->save();
        $server->server_label = 'Renamed server';
        $server->save();

        $updated = $repository->upsertMcp(new McpToolDefinition(
            'search',
            'Changed discovered description',
            ['inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]],
            'changed_capability',
        ), $server);

        self::assertSame($first->id, $updated->id);
        self::assertFalse($updated->active);
        self::assertSame('Administrator description', $updated->description);
        self::assertSame('changed_capability', $updated->capability);
        self::assertSame('renamed-server-search-' . $server->id, $updated->name);
        self::assertSame(['inputSchema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string']]]], $updated->mcp_config);
        self::assertSame(1, AiTool::withoutGlobalScopes()->where('mcp_server_id', $server->id)->where('mcp_name', 'search')->count());
    }
}
