<?php

namespace Tests\Feature\Api;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\Tools\AiTool;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AiModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_list_ai_models(): void
    {
        $this->jsonApi('get', '/api/ai-models')
            ->assertUnauthorized()
            ->assertJson(['errors' => [['detail' => 'Unauthenticated.']]]);
    }

    public function test_can_list_ai_models(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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
            'input' => ['text', 'image'],
            'output' => ['text'],
            'tools' => ['stream'],
            'default_params' => ['temperature' => 0.7],
            'provider_id' => $provider->id,
        ]);

        $response = $this->jsonApi('get', '/api/ai-models')
            ->assertOk();

        $data = collect($response->json('data'));
        $modelResource = $data->first(fn($item) => $item['id'] === (string) $model->id);

        $response->assertJson([
            'data' => [
                array_search($modelResource, $data->all()) => [
                    'id' => (string) $model->id,
                    'type' => 'ai-models',
                    'attributes' => [
                        'active' => true,
                        'model_id' => 'test-model-1',
                        'label' => 'Test Model',
                        'input' => ['text', 'image'],
                        'output' => ['text'],
                        'tools' => ['stream'],
                        'default_params' => ['temperature' => 0.7],
                        'created_at' => $model->created_at->toJson(),
                        'updated_at' => $model->updated_at->toJson(),
                    ],
                ],
            ],
        ]);
    }

    public function test_can_list_ai_models_with_provider(): void
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

        $response = $this->jsonApi('get', '/api/ai-models?include=provider')
            ->assertOk();

        $data = collect($response->json('data'));
        $modelResource = $data->first(fn($item) => $item['id'] === (string) $model->id);
        $idx = array_search($modelResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $model->id,
                    'type' => 'ai-models',
                    'relationships' => [
                        'provider' => [
                            'data' => [
                                'id' => (string) $provider->id,
                                'type' => 'ai-providers',
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $included = collect($response->json('included'));
        $providerResource = $included->first(fn($item) => $item['type'] === 'ai-providers' && $item['id'] === (string) $provider->id);

        $response->assertJson([
            'included' => [
                array_search($providerResource, $included->all()) => [
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

    public function test_can_list_ai_models_with_assigned_tools(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

        $tool = AiTool::create([
            'type' => 'function',
            'name' => 'assigned-tool',
            'status' => 'active',
        ]);

        $model->assignedTools()->attach($tool->id);

        $response = $this->jsonApi('get', '/api/ai-models?include=assignedTools')
            ->assertOk();

        $data = collect($response->json('data'));
        $modelResource = $data->first(fn($item) => $item['id'] === (string) $model->id);
        $idx = array_search($modelResource, $data->all());

        $response->assertJson([
            'data' => [
                $idx => [
                    'id' => (string) $model->id,
                    'type' => 'ai-models',
                    'relationships' => [
                        'assignedTools' => [
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
                        'name' => 'assigned-tool',
                        'status' => 'active',
                    ],
                ],
            ],
        ]);
    }

    public function test_can_show_single_ai_model(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

        $this->jsonApi('get', "/api/ai-models/{$model->id}")
            ->assertOk()
            ->assertJson([
                'data' => [
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
            ]);
    }

    public function test_ai_models_pagination(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $provider = AiProvider::create([
            'provider_id' => 'test-provider',
            'name' => 'Test Provider',
            'active' => true,
            'api_url' => 'https://api.example.com',
        ]);

        foreach (range(1, 15) as $i) {
            AiModel::create([
                'model_id' => "model-{$i}",
                'label' => "Model {$i}",
                'active' => true,
                'provider_id' => $provider->id,
            ]);
        }

        $response = $this->jsonApi('get', '/api/ai-models?' . http_build_query(['page' => ['size' => 5]]))
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
