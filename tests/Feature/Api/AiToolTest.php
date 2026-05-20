<?php

namespace Tests\Feature\Api;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use App\Models\Ai\Tools\McpServer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiToolTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_ai_tools(): void
    {
        $this->jsonApi('get', '/api/ai-tools')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_ai_tools(): void
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
            'name' => 'my-test-tool',
            'description' => 'A test tool',
            'capability' => 'search',
            'status' => 'active',
            'active' => true,
            'server_id' => $server->id,
        ]);

        $response = $this->jsonApi('get', '/api/ai-tools')
            ->assertOk();

        $data = collect($response->json('data'));
        $toolResource = $data->first(fn($item) => $item['id'] === (string) $tool->id);

        $response->assertJson([
            'data' => [
                array_search($toolResource, $data->all()) => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'attributes' => [
                        'type' => 'mcp',
                        'name' => 'my-test-tool',
                        'class_name' => null,
                        'description' => 'A test tool',
                        'capability' => 'search',
                        'status' => 'active',
                        'active' => true,
                        'inputSchema' => null,
                        'created_at' => $tool->created_at->toJson(),
                        'updated_at' => $tool->updated_at->toJson(),
                    ],
                ],
            ],
        ]);
    }

    public function test_can_list_ai_tools_with_server(): void
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
            'name' => 'my-test-tool',
            'status' => 'active',
            'server_id' => $server->id,
        ]);

        $response = $this->jsonApi('get', '/api/ai-tools?include=server')
            ->assertOk();

        $data = collect($response->json('data'));
        $toolResource = $data->first(fn($item) => $item['id'] === (string) $tool->id);
        $idx = array_search($toolResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'relationships' => [
                        'server' => [
                            'data' => [
                                'id' => (string) $server->id,
                                'type' => 'mcp-servers',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $serverResource = $included->first(fn($item) => $item['type'] === 'mcp-servers' && $item['id'] === (string) $server->id);

        $response->assertJson([
            'included' => [
                array_search($serverResource, $included->all()) => [
                    'id' => (string) $server->id,
                    'type' => 'mcp-servers',
                    'attributes' => [
                        'url' => 'https://example.com/mcp',
                        'server_label' => 'Test Server',
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
            ],
        ]);

        $this->assertArrayNotHasKey('api_key', $serverResource['attributes']);
    }

    public function test_can_list_ai_tools_with_models(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'my-model-tool',
            'status' => 'active',
        ]);

        $provider = AiProvider::create([
            'provider_id' => 'test-provider',
            'name' => 'Test Provider',
            'active' => true,
            'api_url' => 'https://api.example.com',
        ]);

        $model = AiModel::create([
            'model_id' => 'test-model-1',
            'label' => 'Test Model',
            'active' => true,
            'provider_id' => $provider->id,
        ]);

        $tool->models()->attach($model->id);

        $response = $this->jsonApi('get', '/api/ai-tools?include=models')
            ->assertOk();

        $data = collect($response->json('data'));
        $toolResource = $data->first(fn($item) => $item['id'] === (string) $tool->id);
        $idx = array_search($toolResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'relationships' => [
                        'models' => [
                            'data' => [
                                [
                                    'id' => (string) $model->id,
                                    'type' => 'ai-models',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $modelResource = $included->first(fn($item) => $item['type'] === 'ai-models' && $item['id'] === (string) $model->id);

        $response->assertJson([
            'included' => [
                array_search($modelResource, $included->all()) => [
                    'id' => (string) $model->id,
                    'type' => 'ai-models',
                    'attributes' => [
                        'active' => true,
                        'model_id' => 'test-model-1',
                        'label' => 'Test Model',
                        'input' => null,
                        'output' => null,
                        'tools' => null,
                        'default_params' => null,
                        'created_at' => $model->created_at->toJson(),
                        'updated_at' => $model->updated_at->toJson(),
                    ],
                ],
            ],
        ]);
    }

    public function test_ai_tool_without_server_has_null_server_relationship(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'standalone-tool',
            'status' => 'active',
        ]);

        $response = $this->jsonApi('get', '/api/ai-tools')
            ->assertOk();

        $data = collect($response->json('data'));
        $toolResource = $data->first(fn($item) => $item['id'] === (string) $tool->id);

        $response->assertJson([
            'data' => [
                array_search($toolResource, $data->all()) => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'attributes' => [
                        'type' => 'function',
                        'name' => 'standalone-tool',
                        'status' => 'active',
                    ],
                ],
            ],
        ]);

        $this->assertNull($toolResource['relationships']['server']['data'] ?? null);
    }

    public function test_ai_tools_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 15) as $i) {
            AiTool::create([
                'type' => 'function',
                'name' => "tool-{$i}",
                'status' => 'active',
            ]);
        }

        $response = $this->jsonApi('get', '/api/ai-tools?' . http_build_query(['page' => ['size' => 5]]))
            ->assertOk()
            ->assertJsonCount(5, 'data');

        $response->assertJsonStructure([
            'meta' => [
                'page' => ['currentPage', 'from', 'to', 'perPage', 'lastPage', 'total'],
            ],
            'links' => ['first', 'last', 'next'],
        ]);
    }

    public function test_can_show_single_ai_tool(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'single-tool',
            'description' => 'Single tool desc',
            'status' => 'active',
        ]);

        $this->jsonApi('get', "/api/ai-tools/{$tool->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $tool->id,
                    'type' => 'ai-tools',
                    'attributes' => [
                        'type' => 'function',
                        'name' => 'single-tool',
                        'class_name' => null,
                        'description' => 'Single tool desc',
                        'capability' => null,
                        'status' => 'active',
                        'active' => true,
                        'inputSchema' => null,
                        'created_at' => $tool->created_at->toJson(),
                        'updated_at' => $tool->updated_at->toJson(),
                    ],
                ],
            ]);
    }
}
