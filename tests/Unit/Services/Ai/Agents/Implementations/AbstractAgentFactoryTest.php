<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Implementations;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Ai\Agents\Implementations\AbstractAgentFactory;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use App\Services\System\UsageTypes\UsageContext;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AbstractAgentFactory::class)]
class AbstractAgentFactoryTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Creates a concrete anonymous subclass of AbstractAgentFactory that exposes
     * the protected createRequestContext() method for testing and always returns null
     * from createAgent().
     */
    private function makeSut(): AbstractAgentFactory
    {
        return new class extends AbstractAgentFactory {
            public function createAgent(mixed $request): AgentInterface|null
            {
                return null;
            }

            public function callCreateRequestContext(
                AiModel            $model,
                ?AiModelParameters $parameters = null,
                ?AiProvider        $provider = null,
                ?string            $usageType = null
            ): AgentRequestContext {
                return $this->createRequestContext($model, $parameters, $provider, $usageType);
            }
        };
    }

    private function makeModel(): AiModel&MockObject
    {
        $model = $this->createMock(AiModel::class);
        $params = new AiModelParameters(['temperature' => 0.5]);
        $model->method('__get')->willReturnCallback(
            fn(string $key) => $key === 'parameters' ? $params : null
        );
        return $model;
    }

    private function makeProxy(): AiProviderProxy
    {
        // AiProviderProxy is readonly and must not be mocked — build a real instance
        return new AiProviderProxy(
            provider: new AiProvider(),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Provider::class),
        );
    }

    private function makeProxyResolver(AiProviderProxy $proxy): AiProviderProxyResolver&MockObject
    {
        $resolver = $this->createMock(AiProviderProxyResolver::class);
        $resolver->method('resolveForModel')->willReturn($proxy);
        $resolver->method('resolve')->willReturn($proxy);
        return $resolver;
    }

    private function makeUsageContext(string $resolved = 'main_app'): UsageContext&MockObject
    {
        $ctx = $this->createMock(UsageContext::class);
        $ctx->method('getForGiven')->willReturn($resolved);
        return $ctx;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(AbstractAgentFactory::class, $sut);
        static::assertInstanceOf(AgentFactoryInterface::class, $sut);
    }

    // =========================================================================
    // setToolResolver / setProviderProxyResolver / setUsageContext
    // =========================================================================

    public function testItAcceptsToolResolverViaSetter(): void
    {
        $sut = $this->makeSut();
        $resolver = $this->createMock(LaravelToolResolver::class);
        // No exception means the setter accepted the value
        $sut->setToolResolver($resolver);
        static::assertTrue(true);
    }

    public function testItAcceptsProviderProxyResolverViaSetter(): void
    {
        $sut = $this->makeSut();
        $resolver = $this->makeProxyResolver($this->makeProxy());
        $sut->setProviderProxyResolver($resolver);
        static::assertTrue(true);
    }

    public function testItAcceptsUsageContextViaSetter(): void
    {
        $sut = $this->makeSut();
        $usageCtx = $this->makeUsageContext();
        $sut->setUsageContext($usageCtx);
        static::assertTrue(true);
    }

    // =========================================================================
    // createRequestContext — model parameters fallback
    // =========================================================================

    public function testItUsesModelParametersWhenNoneExplicitlyProvided(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();

        $sut->setProviderProxyResolver($this->makeProxyResolver($proxy));
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model);

        // Model parameters are cloned from the model's own parameters
        static::assertInstanceOf(AiModelParameters::class, $context->modelParameters);
    }

    public function testItUsesExplicitParametersWhenProvided(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();
        $explicitParams = new AiModelParameters(['temperature' => 0.1]);

        $sut->setProviderProxyResolver($this->makeProxyResolver($proxy));
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model, $explicitParams);

        static::assertSame($explicitParams, $context->modelParameters);
    }

    // =========================================================================
    // createRequestContext — provider proxy resolution
    // =========================================================================

    public function testItResolvesProviderProxyForModelWhenNoProviderOverride(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();

        $resolver = $this->createMock(AiProviderProxyResolver::class);
        $resolver->expects(static::once())->method('resolveForModel')->with($model)->willReturn($proxy);
        $resolver->expects(static::never())->method('resolve');

        $sut->setProviderProxyResolver($resolver);
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model);

        static::assertSame($proxy, $context->provider);
    }

    public function testItResolvesProviderProxyFromExplicitProviderWhenGiven(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();
        $provider = $this->createMock(AiProvider::class);

        $resolver = $this->createMock(AiProviderProxyResolver::class);
        $resolver->expects(static::never())->method('resolveForModel');
        $resolver->expects(static::once())->method('resolve')->with($provider)->willReturn($proxy);

        $sut->setProviderProxyResolver($resolver);
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model, null, $provider);

        static::assertSame($proxy, $context->provider);
    }

    // =========================================================================
    // createRequestContext — usage type
    // =========================================================================

    public function testItNormalisesUsageTypeThroughUsageContext(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();

        $usageCtx = $this->createMock(UsageContext::class);
        $usageCtx->expects(static::once())
            ->method('getForGiven')
            ->with('custom_type')
            ->willReturn('resolved_type');

        $sut->setProviderProxyResolver($this->makeProxyResolver($proxy));
        $sut->setUsageContext($usageCtx);

        $context = $sut->callCreateRequestContext($model, null, null, 'custom_type');

        static::assertSame('resolved_type', $context->usageType);
    }

    public function testItPassesNullUsageTypeToContextWhenNotProvided(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();
        $proxy = $this->makeProxy();

        $usageCtx = $this->createMock(UsageContext::class);
        $usageCtx->expects(static::once())
            ->method('getForGiven')
            ->with(null)
            ->willReturn('main_app');

        $sut->setProviderProxyResolver($this->makeProxyResolver($proxy));
        $sut->setUsageContext($usageCtx);

        $context = $sut->callCreateRequestContext($model);

        static::assertSame('main_app', $context->usageType);
    }

    // =========================================================================
    // createRequestContext — returned context
    // =========================================================================

    public function testItReturnsAgentRequestContextInstance(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();

        $sut->setProviderProxyResolver($this->makeProxyResolver($this->makeProxy()));
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model);

        static::assertInstanceOf(AgentRequestContext::class, $context);
    }

    public function testItSetsModelOnContext(): void
    {
        $sut = $this->makeSut();
        $model = $this->makeModel();

        $sut->setProviderProxyResolver($this->makeProxyResolver($this->makeProxy()));
        $sut->setUsageContext($this->makeUsageContext());

        $context = $sut->callCreateRequestContext($model);

        static::assertSame($model, $context->model);
    }
}
