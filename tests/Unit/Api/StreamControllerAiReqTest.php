<?php

namespace Tests\Feature;

use App\Http\Middleware\ExternalCommunicationCheck;
use App\Models\User;
use App\Services\AI\AiFactory;
use App\Services\AI\AiService;
use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiModelCollection;
use App\Services\AI\Value\AiModelContext;
use App\Services\AI\Value\AiModelMap;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\AvailableAiModels;
use App\Services\AI\Value\ModelOnlineStatus;
use App\Services\AI\Value\TokenUsage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Support\Facades\Config;

class StreamControllerAiReqTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ExternalCommunicationCheck::class);
        $this->user = $this->makeTestUser();
    }

    private function makeTestUser(): User
    {
        return User::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'username' => 'testuser',
            'publicKey' => str_repeat('x', 32),
            'employeetype' => 'staff',
        ]);
    }

    private function makeAiResponse(array $content = [['type' => 'text', 'text' => 'Hello!']]): AiResponse
    {
        return new AiResponse(
            content: $content,
            usage: new TokenUsage(
                new AiModel(['id' => 'test-model']),
                10,
                20,
            ),
        );
    }

    private function makeValidPayload(string $model = 'gpt-4'): array
    {
        return [
            'payload' => [
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hello']],
                ],
            ],
        ];
    }

    private function bindMockAiService(AiResponse $response, string $modelId = 'gpt-4'): void
    {
        $mockClient = \Mockery::mock(ClientInterface::class);
        $mockClient->shouldReceive('sendRequest')->once()->andReturn($response);

        $aiModel = new AiModel(['id' => $modelId]);
        AiModel::bindContext($aiModel, new AiModelContext(
            $aiModel,
            \Mockery::mock(ModelProviderInterface::class),
            fn() => $mockClient,
            fn() => ModelOnlineStatus::ONLINE,
        ));

        $availableModels = new AvailableAiModels(
            new AiModelCollection($aiModel),
            new AiModelMap(),
            new AiModelMap(),
        );

        $mockFactory = \Mockery::mock(AiFactory::class);
        $mockFactory->shouldReceive('getAvailableModels')->andReturn($availableModels);
        $this->app->instance(AiService::class, new AiService($mockFactory));
    }

    private function bindMockUsageAnalyzer(): void
    {
        $this->mock(UsageAnalyzerService::class)
            ->shouldReceive('submitUsageRecord');
    }


    public function test_returns_200_with_valid_request(): void
    {
        $aiResponse = $this->makeAiResponse();
        $this->bindMockAiService($aiResponse);
        $this->bindMockUsageAnalyzer();

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', $this->makeValidPayload());

        $response->assertOk()->assertJson([
            'success' => true,
            'content' => $aiResponse->content,
        ]);
    }

    public function test_payload_flows_through_to_client(): void
    {
        $payload = $this->makeValidPayload('gpt-4o');
        $aiResponse = $this->makeAiResponse();

        $mockClient = \Mockery::mock(ClientInterface::class);
        $mockClient->shouldReceive('sendRequest')
            ->once()
            ->with(\Mockery::on(function (AiRequest $req) use ($payload) {
                return $req->payload['model'] === $payload['payload']['model'];
            }))
            ->andReturn($aiResponse);

        $aiModel = new AiModel(['id' => 'gpt-4o']);
        AiModel::bindContext($aiModel, new AiModelContext(
            $aiModel,
            \Mockery::mock(ModelProviderInterface::class),
            fn() => $mockClient,
            fn() => ModelOnlineStatus::ONLINE,
        ));

        $mockFactory = \Mockery::mock(AiFactory::class);
        $mockFactory->shouldReceive('getAvailableModels')->andReturn(new AvailableAiModels(
            new AiModelCollection($aiModel),
            new AiModelMap(),
            new AiModelMap(),
        ));
        $this->app->instance(AiService::class, new AiService($mockFactory));

        $this->bindMockUsageAnalyzer();
        Sanctum::actingAs($this->user);

        $this->postJson('/api/ai-req', $payload)->assertOk();
    }

    public function test_records_usage_with_api_type(): void
    {
        $aiResponse = $this->makeAiResponse();
        $this->bindMockAiService($aiResponse);

        $this->mock(UsageAnalyzerService::class)
            ->shouldReceive('submitUsageRecord')
            ->once()
            ->with($aiResponse->usage, 'api');

        Sanctum::actingAs($this->user);

        $this->postJson('/api/ai-req', $this->makeValidPayload())->assertOk();
    }

    public function test_returns_401_without_auth(): void
    {
        $response = $this->postJson('/api/ai-req', $this->makeValidPayload());

        $response->assertUnauthorized();
    }

    public function test_returns_422_when_model_missing(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', [
            'payload' => [
                'messages' => [['role' => 'user', 'content' => ['text' => 'hi']]],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' =>  'Validation Error',
                'errors' => [
                    'payload.model' => [
                        'validation.required',
                    ],
                ],
            ]);
    }

    public function test_returns_422_when_messages_missing(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', [
            'payload' => ['model' => 'gpt-4'],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' =>  'Validation Error',
                'errors' => [
                    'payload.messages' => [
                        'validation.required',
                    ],
                ],
            ]);
    }

    public function test_returns_422_when_message_role_missing(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', [
            'payload' => [
                'model' => 'gpt-4',
                'messages' => [['content' => ['text' => 'hi']]],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' =>  'Validation Error',
                'errors' => [
                    'payload.messages.0.role' => [
                        'validation.required',
                    ],
                ],
            ]);
    }

    public function test_returns_422_when_content_text_missing(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', [
            'payload' => [
                'model' => 'gpt-4',
                'messages' => [['role' => 'user', 'content' => []]],
            ],
        ]);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' =>  'Validation Error',
                'errors' => [
                    'payload.messages.0.content' => [
                        'validation.required',
                    ],
                    'payload.messages.0.content.text' => [
                        'validation.required',
                    ],
                ],
            ]);
    }

    public function test_returns_422_on_empty_body(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', []);

        $response
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' =>  'Validation Error',
                'errors' => [
                    'payload.model' => [
                        'validation.required',
                    ],
                    'payload.messages' => [
                        'validation.required',
                    ],
                ]
            ]);
    }
    public function test_middleware_blocks_request_when_external_communication_is_disabled(): void
    {
        $this->withMiddleware(ExternalCommunicationCheck::class);

        Config::set('sanctum.allow_external_communication', false);

        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/ai-req', $this->makeValidPayload());

        $response->assertStatus(403)
            ->assertJson([
                'response' => "External communication is not allowed. Please contact the administration for more information."
            ]);
    }
}
