<?php

namespace Tests\Feature\Api;

use App\Models\Ai\Tools\AiTool;
use App\Models\Ai\Tools\McpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class McpServerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_mcp_servers(): void
    {
        $this->jsonApi('get', '/api/mcp-servers')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_mcp_servers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $server = McpServer::create([
            'url' => 'https://example.com/mcp',
            'server_label' => 'Test Server',
            'version' => '1.0',
            'protocolVersion' => '2024-11-05',
            'description' => 'A test MCP server',
            'require_approval' => 'never',
            'timeout' => '15',
            'discovery_timeout' => '20',
        ]);

        $response = $this->jsonApi('get', '/api/mcp-servers')
            ->assertOk();

        $data = collect($response->json('data'));
        $serverResource = $data->first(fn($item) => $item['id'] === (string) $server->id);

        $response->assertJson([
            'data' => [
                array_search($serverResource, $data->all()) => [
                    'id' => (string) $server->id,
                    'type' => 'mcp-servers',
                    'attributes' => [
                        'url' => 'https://example.com/mcp',
                        'server_label' => 'Test Server',
                        'version' => '1.0',
                        'protocolVersion' => '2024-11-05',
                        'description' => 'A test MCP server',
                        'require_approval' => 'never',
                        'timeout' => '15',
                        'discovery_timeout' => '20',
                        'created_at' => $server->created_at->toJson(),
                        'updated_at' => $server->updated_at->toJson(),
                    ],
                ],
            ],
        ]);
    }

    public function test_mcp_server_does_not_expose_api_key(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        McpServer::create([
            'url' => 'https://example.com/mcp',
            'server_label' => 'Secure Server',
            'timeout' => '10',
            'discovery_timeout' => '10',
            'api_key' => 'super-secret-key',
        ]);

        $response = $this->jsonApi('get', '/api/mcp-servers')
            ->assertOk();

        foreach ($response->json('data') as $resource) {
            $this->assertArrayNotHasKey('api_key', $resource['attributes']);
        }
    }

    public function test_can_list_mcp_servers_with_tools(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $server = McpServer::create([
            'url' => 'https://example.com/mcp',
            'server_label' => 'Test Server',
            'timeout' => '10',
            'discovery_timeout' => '10',
        ]);

        $tool = AiTool::create([
            'type' => 'mcp',
            'name' => 'server-tool',
            'status' => 'active',
            'server_id' => $server->id,
        ]);

        $response = $this->jsonApi('get', '/api/mcp-servers?include=tools')
            ->assertOk();

        $data = collect($response->json('data'));
        $serverResource = $data->first(fn($item) => $item['id'] === (string) $server->id);
        $idx = array_search($serverResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $server->id,
                    'type' => 'mcp-servers',
                    'relationships' => [
                        'tools' => [
                            'data' => [
                                [
                                    'id' => (string) $tool->id,
                                    'type' => 'ai-tools',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $toolResource = $included->first(fn($item) => $item['type'] === 'ai-tools' && $item['id'] === (string) $tool->id);

        $response->assertJson([
            'included' => [
                array_search($toolResource, $included->all()) => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'attributes' => [
                        'type' => 'mcp',
                        'name' => 'server-tool',
                        'status' => 'active',
                    ],
                ],
            ],
        ]);
    }

    public function test_can_show_single_mcp_server(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $server = McpServer::create([
            'url' => 'https://example.com/mcp',
            'server_label' => 'Single Server',
            'timeout' => '10',
            'discovery_timeout' => '10',
        ]);

        $this->jsonApi('get', "/api/mcp-servers/{$server->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $server->id,
                    'type' => 'mcp-servers',
                    'attributes' => [
                        'url' => 'https://example.com/mcp',
                        'server_label' => 'Single Server',
                        'version' => null,
                        'protocolVersion' => null,
                        'description' => null,
                        'require_approval' => 'never',
                        'timeout' => '10',
                        'discovery_timeout' => '10',
                        'created_at' => $server->created_at->toJson(),
                        'updated_at' => $server->updated_at->toJson(),
                    ],
                ],
            ]);
    }

    public function test_mcp_servers_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 15) as $i) {
            McpServer::create([
                'url' => "https://example.com/mcp/{$i}",
                'server_label' => "Server {$i}",
                'timeout' => '10',
                'discovery_timeout' => '10',
            ]);
        }

        $response = $this->jsonApi('get', '/api/mcp-servers?' . http_build_query(['page' => ['size' => 5]]))
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $response->assertJsonStructure([
            'meta' => [
                'page' => ['currentPage', 'from', 'to', 'perPage', 'lastPage', 'total'],
            ],
            'links' => ['first', 'last', 'next'],
        ]);
    }
}
