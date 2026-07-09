<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Implementations\Chat;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Exceptions\InvalidToolTransferStringException;
use App\Services\Ai\Agents\Implementations\Chat\ChatToolResolver;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry;
use App\Services\Ai\Models\Capabilities\Values\AiModelCapabilityDefinition;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\LaravelAi\LaravelToolResolver;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Providers\Provider;
use Laravel\Ai\Providers\Tools\ProviderTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ChatToolResolver::class)]
class ChatToolResolverTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeCapabilityRegistry(
        ?AiModelCapabilityDefinition $definition = null,
        string $capabilityKey = 'web_search'
    ): AiModelCapabilityRegistry&MockObject {
        $registry = $this->createMock(AiModelCapabilityRegistry::class);
        $registry->method('getDefinition')
            ->willReturnCallback(fn(string $key) => $key === $capabilityKey ? $definition : null);
        return $registry;
    }

    private function makeCapabilityDefinition(string $key = 'web_search'): AiModelCapabilityDefinition
    {
        return new AiModelCapabilityDefinition(
            key: $key,
            titleLabel: null,
            descriptionLabel: null,
            iconPath: null
        );
    }

    private function makeToolResolver(): LaravelToolResolver&MockObject
    {
        return $this->createMock(LaravelToolResolver::class);
    }

    private function makeNativeCapabilities(bool $hasCapability = false, string $key = 'web_search'): NativeAiModelCapabilities
    {
        // NativeAiModelCapabilities is final — use fromArray() to build a real instance
        return NativeAiModelCapabilities::fromArray($hasCapability ? [$key] : []);
    }

    private function makeModel(bool $hasNativeCapability = false, string $capabilityKey = 'web_search'): AiModel&MockObject
    {
        $nativeCaps = $this->makeNativeCapabilities($hasNativeCapability, $capabilityKey);
        $model = $this->createMock(AiModel::class);
        // AiModel exposes cast properties via Eloquent's magic __get
        $model->method('__get')->willReturnCallback(
            fn(string $k) => $k === 'native_capabilities' ? $nativeCaps : null
        );
        return $model;
    }

    private function makeProxy(): AiProviderProxy
    {
        return new AiProviderProxy(
            provider: $this->createMock(AiProvider::class),
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->createMock(Provider::class),
        );
    }

    private function makeContext(bool $hasNativeCapability = false, string $capabilityKey = 'web_search'): AgentRequestContext
    {
        return new AgentRequestContext(
            provider: $this->makeProxy(),
            model: $this->makeModel($hasNativeCapability, $capabilityKey),
            modelParameters: new AiModelParameters(),
        );
    }

    private function makeTool(): Tool&MockObject
    {
        return $this->createMock(Tool::class);
    }

    private function makeProviderTool(): ProviderTool&MockObject
    {
        return $this->createMock(ProviderTool::class);
    }

    private function makeSut(
        AiModelCapabilityRegistry $capabilityRegistry,
        LaravelToolResolver $laravelToolResolver
    ): ChatToolResolver {
        return new ChatToolResolver($capabilityRegistry, $laravelToolResolver);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut(
            $this->createMock(AiModelCapabilityRegistry::class),
            $this->createMock(LaravelToolResolver::class)
        );
        static::assertInstanceOf(ChatToolResolver::class, $sut);
    }

    // =========================================================================
    // findTools — validation
    // =========================================================================

    public function testItThrowsWhenTransferStringIsNotAString(): void
    {
        $sut = $this->makeSut(
            $this->createMock(AiModelCapabilityRegistry::class),
            $this->createMock(LaravelToolResolver::class)
        );

        $this->expectException(InvalidToolTransferStringException::class);
        $this->expectExceptionMessage('array of strings');

        [...$sut->findTools([42], $this->makeContext())];
    }

    public function testItReturnsEmptyWhenNoTransferStrings(): void
    {
        $sut = $this->makeSut(
            $this->createMock(AiModelCapabilityRegistry::class),
            $this->createMock(LaravelToolResolver::class)
        );

        $result = [...$sut->findTools([], $this->makeContext())];
        static::assertSame([], $result);
    }

    // =========================================================================
    // findTools — tool by name
    // =========================================================================

    public function testItResolvesToolByName(): void
    {
        $tool = $this->makeTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::once())
            ->method('resolveToolByName')
            ->with('my_tool', static::anything(), [])
            ->willReturn($tool);

        $sut = $this->makeSut($this->makeCapabilityRegistry(), $laravelResolver);

        $result = [...$sut->findTools(['my_tool'], $this->makeContext())];

        static::assertCount(1, $result);
        static::assertSame($tool, $result[0]);
    }

    public function testItResolvesToolByNameWithJsonSettings(): void
    {
        $tool = $this->makeTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::once())
            ->method('resolveToolByName')
            ->with('my_tool', static::anything(), ['param' => 'value'])
            ->willReturn($tool);

        $sut = $this->makeSut($this->makeCapabilityRegistry(), $laravelResolver);

        $result = [...$sut->findTools(['my_tool:{"param":"value"}'], $this->makeContext())];

        static::assertCount(1, $result);
        static::assertSame($tool, $result[0]);
    }

    // =========================================================================
    // findTools — capability:auto — model has native capability
    // =========================================================================

    public function testItResolvesNativeToolForCapabilityWhenModelHasNativeSupport(): void
    {
        $nativeTool = $this->makeProviderTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::once())
            ->method('resolveNativeToolForCapability')
            ->with('web_search', static::anything(), [])
            ->willReturn($nativeTool);
        $laravelResolver->expects(static::never())
            ->method('resolveToolForCapability');

        $sut = $this->makeSut(
            $this->makeCapabilityRegistry($this->makeCapabilityDefinition('web_search'), 'web_search'),
            $laravelResolver
        );

        $result = [...$sut->findTools(
            ['capability:web_search:auto'],
            $this->makeContext(hasNativeCapability: true, capabilityKey: 'web_search')
        )];

        static::assertCount(1, $result);
        static::assertSame($nativeTool, $result[0]);
    }

    // =========================================================================
    // findTools — capability:auto — model does NOT have native capability
    // =========================================================================

    public function testItResolvesHawkiToolForCapabilityWhenModelLacksNativeSupport(): void
    {
        $tool = $this->makeTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::never())
            ->method('resolveNativeToolForCapability');
        $laravelResolver->expects(static::once())
            ->method('resolveToolForCapability')
            ->with('web_search', static::anything(), [])
            ->willReturn($tool);

        $sut = $this->makeSut(
            $this->makeCapabilityRegistry($this->makeCapabilityDefinition('web_search'), 'web_search'),
            $laravelResolver
        );

        $result = [...$sut->findTools(
            ['capability:web_search:auto'],
            $this->makeContext(hasNativeCapability: false, capabilityKey: 'web_search')
        )];

        static::assertCount(1, $result);
        static::assertSame($tool, $result[0]);
    }

    // =========================================================================
    // findTools — capability:native
    // =========================================================================

    public function testItForcesNativeToolForCapabilityWhenNativeKeyword(): void
    {
        $nativeTool = $this->makeProviderTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::once())
            ->method('resolveNativeToolForCapability')
            ->willReturn($nativeTool);

        $sut = $this->makeSut(
            $this->makeCapabilityRegistry($this->makeCapabilityDefinition('web_search'), 'web_search'),
            $laravelResolver
        );

        $result = [...$sut->findTools(
            ['capability:web_search:native'],
            $this->makeContext(hasNativeCapability: false)
        )];

        static::assertCount(1, $result);
        static::assertSame($nativeTool, $result[0]);
    }

    // =========================================================================
    // findTools — capability:<specific tool name>
    // =========================================================================

    public function testItResolvesSpecificToolByNameForCapability(): void
    {
        $tool = $this->makeTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->expects(static::once())
            ->method('resolveToolByName')
            ->with('specific_tool', static::anything(), [])
            ->willReturn($tool);

        $sut = $this->makeSut(
            $this->makeCapabilityRegistry($this->makeCapabilityDefinition('web_search'), 'web_search'),
            $laravelResolver
        );

        $result = [...$sut->findTools(
            ['capability:web_search:specific_tool'],
            $this->makeContext()
        )];

        static::assertCount(1, $result);
        static::assertSame($tool, $result[0]);
    }

    // =========================================================================
    // findTools — unknown capability
    // =========================================================================

    public function testItThrowsWhenCapabilityNotRegistered(): void
    {
        $sut = $this->makeSut(
            $this->makeCapabilityRegistry(null, 'web_search'),
            $this->makeToolResolver()
        );

        $this->expectException(InvalidToolTransferStringException::class);
        $this->expectExceptionMessage('"unknown_cap"');

        [...$sut->findTools(['capability:unknown_cap:auto'], $this->makeContext())];
    }

    // =========================================================================
    // findTools — multiple tools
    // =========================================================================

    public function testItResolvesMultipleToolsInOrder(): void
    {
        $tool1 = $this->makeTool();
        $tool2 = $this->makeTool();

        $laravelResolver = $this->makeToolResolver();
        $laravelResolver->method('resolveToolByName')
            ->willReturnOnConsecutiveCalls($tool1, $tool2);

        $sut = $this->makeSut($this->makeCapabilityRegistry(), $laravelResolver);

        $result = [...$sut->findTools(['tool_a', 'tool_b'], $this->makeContext())];

        static::assertCount(2, $result);
        static::assertSame($tool1, $result[0]);
        static::assertSame($tool2, $result[1]);
    }
}
