<?php

namespace Tests\Feature\Api;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelStatus;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_ai_providers(): void
    {
        $this->jsonApi('get', '/api/ai-providers')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_ai_providers(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
        ]);

        $response = $this->jsonApi('get', '/api/ai-providers')
            ->assertOk();

        $data = collect($response->json('data'));
        $providerResource = $data->first(fn ($item) => $item['id'] === (string) $provider->id);

        $response->assertJson([
            'data' => [
                array_search($providerResource, $data->all()) => [
                    'id' => (string) $provider->id,
                    'type' => 'ai-providers',
                    'attributes' => [
                        'provider_id' => 'openai',
                        'name' => 'OpenAI',
                        'active' => true,
                        'api_url' => 'https://api.openai.com',
                        'ping_url' => null,
                        'created_at' => $provider->created_at->toJson(),
                        'updated_at' => $provider->updated_at->toJson(),
                    ],
                ],
            ],
        ]);
    }

    public function test_can_list_ai_providers_with_models(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
        ]);

        $model = AiModel::create([
            'model_id' => 'gpt-4',
            'label' => 'GPT-4',
            'active' => true,
            'provider_id' => $provider->id,
        ]);

        $response = $this->jsonApi('get', '/api/ai-providers?include=models')
            ->assertOk();

        $data = collect($response->json('data'));
        $providerResource = $data->first(fn ($item) => $item['id'] === (string) $provider->id);
        $idx = array_search($providerResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $provider->id,
                    'type' => 'ai-providers',
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
        $modelResource = $included->first(fn ($item) => $item['type'] === 'ai-models' && $item['id'] === (string) $model->id);

        $response->assertJson([
            'included' => [
                array_search($modelResource, $included->all()) => [
                    'id' => (string) $model->id,
                    'type' => 'ai-models',
                    'attributes' => [
                        'active' => true,
                        'model_id' => 'gpt-4',
                        'label' => 'GPT-4',
                    ],
                ],
            ],
        ]);
    }

    public function test_can_list_ai_providers_with_nested_models_and_assigned_tools(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
        ]);

        $model = AiModel::create([
            'model_id' => 'gpt-4',
            'label' => 'GPT-4',
            'active' => true,
            'provider_id' => $provider->id,
        ]);

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'web-search',
            'status' => 'active',
        ]);

        $model->assignedTools()->attach($tool->id);

        $response = $this->jsonApi('get', '/api/ai-providers?include=models.assignedTools')
            ->assertOk();

        $data = collect($response->json('data'));
        $providerResource = $data->first(fn ($item) => $item['id'] === (string) $provider->id);
        $idx = array_search($providerResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $provider->id,
                    'type' => 'ai-providers',
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

        $modelResource = $included->first(fn ($item) => $item['type'] === 'ai-models' && $item['id'] === (string) $model->id);
        $this->assertNotNull($modelResource, 'Model should be included');
        $this->assertEquals([
            ['id' => (string) $tool->id, 'type' => 'ai-tools'],
        ], $modelResource['relationships']['assignedTools']['data']);

        $toolResource = $included->first(fn ($item) => $item['type'] === 'ai-tools' && $item['id'] === (string) $tool->id);
        $this->assertNotNull($toolResource, 'Tool should be included');
        $this->assertEquals('web-search', $toolResource['attributes']['name']);
        $this->assertEquals('active', $toolResource['attributes']['status']);
    }

    public function test_can_list_ai_providers_with_nested_models_and_status(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
        ]);

        $model = AiModel::create([
            'model_id' => 'gpt-4',
            'label' => 'GPT-4',
            'active' => true,
            'provider_id' => $provider->id,
        ]);

        AiModelStatus::create([
            'model_id' => $model->model_id,
            'status' => 'online',
        ]);

        $response = $this->jsonApi('get', '/api/ai-providers?include=models.status')
            ->assertOk();

        $data = collect($response->json('data'));
        $providerResource = $data->first(fn ($item) => $item['id'] === (string) $provider->id);

        $response->assertJson([
            'data' => [
                array_search($providerResource, $data->all()) => [
                    'id' => (string) $provider->id,
                    'type' => 'ai-providers',
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

        $modelResource = $included->first(fn ($item) => $item['type'] === 'ai-models' && $item['id'] === (string) $model->id);
        $this->assertNotNull($modelResource, 'Model should be included');
        $this->assertEquals([
            'id' => $model->model_id,
            'type' => 'ai-model-statuses',
        ], $modelResource['relationships']['status']['data']);

        $statusResource = $included->first(fn ($item) => $item['type'] === 'ai-model-statuses' && $item['id'] === $model->model_id);
        $this->assertNotNull($statusResource, 'Status should be included');
        $this->assertEquals('online', $statusResource['attributes']['status']);
    }

    public function test_can_show_single_ai_provider(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
            'ping_url' => 'https://api.openai.com/ping',
        ]);

        $this->jsonApi('get', "/api/ai-providers/{$provider->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'id' => (string) $provider->id,
                    'type' => 'ai-providers',
                    'attributes' => [
                        'provider_id' => 'openai',
                        'name' => 'OpenAI',
                        'active' => true,
                        'api_url' => 'https://api.openai.com',
                        'ping_url' => 'https://api.openai.com/ping',
                        'created_at' => $provider->created_at->toJson(),
                        'updated_at' => $provider->updated_at->toJson(),
                    ],
                ],
            ]);
    }

    public function test_can_filter_ai_providers_by_tool_capability(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $providerA = AiProvider::create([
            'provider_id' => 'openai',
            'name' => 'OpenAI',
            'active' => true,
            'api_url' => 'https://api.openai.com',
        ]);

        $providerB = AiProvider::create([
            'provider_id' => 'anthropic',
            'name' => 'Anthropic',
            'active' => true,
            'api_url' => 'https://api.anthropic.com',
        ]);

        $modelA = AiModel::create([
            'model_id' => 'gpt-4',
            'label' => 'GPT-4',
            'active' => true,
            'provider_id' => $providerA->id,
        ]);

        $modelB = AiModel::create([
            'model_id' => 'claude-3',
            'label' => 'Claude 3',
            'active' => true,
            'provider_id' => $providerB->id,
        ]);

        $toolWebSearch = AiTool::create([
            'type' => 'function',
            'name' => 'web-search',
            'status' => 'active',
            'capability' => 'web-search',
        ]);

        $toolCodeExec = AiTool::create([
            'type' => 'function',
            'name' => 'code-exec',
            'status' => 'active',
            'capability' => 'code-execution',
        ]);

        $modelA->assignedTools()->attach($toolWebSearch->id);
        $modelB->assignedTools()->attach($toolCodeExec->id);

        $response = $this->jsonApi('get', '/api/ai-providers?' . http_build_query(['filter' => ['tool_capability' => 'web-search']]))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $providerA->id, $ids);
        $this->assertNotContains((string) $providerB->id, $ids);

        $response = $this->jsonApi('get', '/api/ai-providers?' . http_build_query(['filter' => ['tool_capability' => 'code-execution']]))
            ->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains((string) $providerB->id, $ids);
        $this->assertNotContains((string) $providerA->id, $ids);
    }

    public function test_ai_providers_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        foreach (range(1, 15) as $i) {
            AiProvider::create([
                'provider_id' => "provider-{$i}",
                'name' => "Provider {$i}",
                'active' => true,
                'api_url' => "https://api{$i}.example.com",
            ]);
        }

        $response = $this->jsonApi('get', '/api/ai-providers?' . http_build_query(['page' => ['size' => 5]]))
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
