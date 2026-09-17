<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\User;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Access\Exceptions\ModelAccessException;
use App\Services\Ai\Models\Access\ModelAuthorization;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Tools\LaravelAi\AuthorizedTextGateway;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversNothing()]
class ModelDispatchAuthorizationTest extends TestCase
{
    use DatabaseTransactions;

    public function testDisabledActorCannotDispatchAnUnrestrictedModel(): void
    {
        [$actor, $context] = $this->context();
        DB::table('users')->where('id', $actor->id)->update(['admin_disabled' => true]);

        $this->expectException(ModelAccessException::class);
        app(ModelAuthorization::class)->authorize($context);
    }

    public function testInactiveModelCannotBeDispatchedFromAnExistingContext(): void
    {
        [, $context, $model] = $this->context();
        $model->update(['active' => false]);

        $this->expectException(ModelAccessException::class);
        app(ModelAuthorization::class)->authorize($context);
    }

    public function testExplicitActorlessContextCanDispatch(): void
    {
        [, $context, $model] = $this->context();
        $actorless = new AgentRequestContext(
            $context->provider,
            $context->model,
            $context->modelParameters,
            $context->usageType,
        );

        self::assertTrue(app(ModelAuthorization::class)->authorizeCurrent($actorless)->is($model));
    }

    public function testEveryProviderStepRechecksModelAuthorizationWithoutTools(): void
    {
        [, $context, $model] = $this->context();
        $gateway = $this->createMock(StepTextGateway::class);
        $gateway->expects($this->once())->method('generateTextStep')->willReturn(self::createStub(StepResponse::class));
        $authorized = new AuthorizedTextGateway($gateway, $context);
        $driver = $context->provider->driver;

        $authorized->generateTextStep($driver, $model->model_id, '', [], [], null, null, null, new StepContext(0, false));
        $model->usageRules()->delete();

        $this->expectException(ModelAccessException::class);
        $authorized->generateTextStep($driver, $model->model_id, '', [], [], null, null, null, new StepContext(1, false));
    }

    #[DataProvider('invalidRuntimeStates')]
    public function testDispatchRejectsChangedRuntimeConfiguration(string $change): void
    {
        [, $context, $model, $provider] = $this->context();

        match ($change) {
            'provider inactive' => DB::table('ai_providers')->where('id', $provider->id)->update(['active' => false]),
            'usage removed' => $model->usageRules()->delete(),
            'model identifier changed' => DB::table('ai_models')->where('id', $model->id)->update(['model_id' => $model->model_id . '-replacement']),
            'provider credentials changed' => DB::table('ai_providers')->where('id', $provider->id)->update(['api_key' => 'replacement-key']),
            'provider binding changed' => DB::table('ai_models')->where('id', $model->id)->update(['provider_id' => $this->replacementProvider($provider)->id]),
        };

        $this->expectException(ModelAccessException::class);
        app(ModelAuthorization::class)->authorize($context);
    }

    public static function invalidRuntimeStates(): iterable
    {
        yield from [
            'provider inactive' => ['provider inactive'],
            'usage removed' => ['usage removed'],
            'model identifier changed' => ['model identifier changed'],
            'provider credentials changed' => ['provider credentials changed'],
            'provider binding changed' => ['provider binding changed'],
        ];
    }

    private function replacementProvider(AiProvider $provider): AiProvider
    {
        return AiProvider::create([
            'provider_id' => $provider->provider_id . '-replacement',
            'name' => 'Replacement provider',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://replacement.example.invalid',
            'api_key' => 'replacement-key',
        ]);
    }

    /**
     * @return array{0: User, 1: AgentRequestContext, 2: AiModel, 3: AiProvider}
     */
    private function context(): array
    {
        $actor = User::factory()->create();
        $provider = AiProvider::create([
            'provider_id' => 'dispatch-auth-' . $actor->id,
            'name' => 'Dispatch authorization test',
            'active' => true,
            'adapter_key' => 'openai',
            'api_url' => 'https://example.invalid',
            'api_key' => 'test-only',
        ]);
        $model = AiModel::create([
            'model_id' => 'dispatch-auth-' . $actor->id,
            'label' => 'Dispatch authorization test',
            'provider_id' => $provider->id,
            'active' => true,
            'status' => OnlineStatus::ONLINE,
            'flags' => AiModelFlags::fromArray([]),
            'limits' => new NullAiModelLimits(),
            'pricing' => new NullPricing(),
            'settings' => AiModelSettings::fromArray(['tool_calling' => true]),
            'native_capabilities' => NativeAiModelCapabilities::fromArray([]),
        ]);
        $model->usageRules()->create(['usage_type' => WellKnownUsageTypes::MAIN_APP]);
        $context = new AgentRequestContext(
            app(AiProviderProxyResolver::class)->resolve($provider),
            $model,
            new AiModelParameters(),
            actorId: $actor->id,
        );

        return [$actor, $context, $model, $provider];
    }
}
