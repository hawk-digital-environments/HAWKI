<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron;

use App\Collections\AiToolCollection;
use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Models\Ai\AiTool;
use App\Services\Ai\ProviderAdapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\Ai\Registries\ProviderAdapterRegistry;
use App\Services\Ai\Tools\Neuron\Events\ToolsResolvedFilterEvent;
use App\Services\Ai\Tools\Neuron\NeuronToolConverter;
use App\Services\Ai\Tools\Neuron\NeuronToolProvider;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\ParameterSource;
use Illuminate\Support\Facades\Event;
use NeuronAI\Tools\ProviderTool;
use NeuronAI\Tools\ToolInterface as NeuronToolInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(NeuronToolProvider::class)]
class NeuronToolProviderTest extends TestCase
{
    private ProviderAdapterRegistry&MockObject $adapterRegistry;
    private AiModelCapabilityRegistry&MockObject $capabilityRegistry;
    private NeuronToolConverter&MockObject $toolConverter;
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adapterRegistry = $this->createMock(ProviderAdapterRegistry::class);
        $this->capabilityRegistry = $this->createMock(AiModelCapabilityRegistry::class);
        $this->toolConverter = $this->createMock(NeuronToolConverter::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(NeuronToolProvider::class, $this->makeSut());
    }

    // =========================================================================
    // getTools – capability resolution
    // =========================================================================

    public function testItReturnsProviderToolForNativeCapability(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $providerTool = $this->createMock(ProviderTool::class);
        $this->capabilityRegistry->method('has')->willReturn(true);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::NATIVE]);
        $model = $this->makeModel(capabilities: $capabilities);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: $providerTool));

        $result = $this->makeSut()->getTools($source, ['web_search'], []);

        static::assertSame([$providerTool], $result);
    }

    public function testItReturnsProviderToolForYesCapabilityWhenNoCustomToolLinked(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $providerTool = $this->createMock(ProviderTool::class);
        $this->capabilityRegistry->method('has')->willReturn(true);

        // Model has YES but no tool registered for 'web_search'
        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::YES]);
        $model = $this->makeModel(capabilities: $capabilities, tools: new AiToolCollection());
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: $providerTool));

        $result = $this->makeSut()->getTools($source, ['web_search'], []);

        static::assertSame([$providerTool], $result);
    }

    public function testItReturnsCustomToolForYesCapabilityWhenCustomToolIsLinked(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('web_search_tool', effectiveCapability: 'web_search');
        $neuronTool = $this->createMock(NeuronToolInterface::class);

        $this->capabilityRegistry->method('has')->willReturn(true);
        $this->toolConverter->method('convert')->with($aiTool)->willReturn($neuronTool);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::YES]);
        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(capabilities: $capabilities, tools: $tools);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: null));

        $result = $this->makeSut()->getTools($source, ['web_search'], []);

        static::assertSame([$neuronTool], $result);
    }

    public function testItReturnsCustomToolForToolCapability(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('kb_tool', effectiveCapability: 'knowledge_base');
        $neuronTool = $this->createMock(NeuronToolInterface::class);

        $this->capabilityRegistry->method('has')->willReturn(true);
        $this->toolConverter->method('convert')->with($aiTool)->willReturn($neuronTool);

        $capabilities = $this->makeCapabilities(['knowledge_base' => ModelCapabilityValueType::TOOL]);
        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(capabilities: $capabilities, tools: $tools);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: null));

        $result = $this->makeSut()->getTools($source, ['knowledge_base'], []);

        static::assertSame([$neuronTool], $result);
    }

    public function testItReturnsNothingForNoCapabilityRule(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $this->capabilityRegistry->method('has')->willReturn(true);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::NO]);
        $model = $this->makeModel(capabilities: $capabilities);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: null));

        $result = $this->makeSut()->getTools($source, ['web_search'], []);

        static::assertSame([], $result);
    }

    public function testItSkipsUnregisteredCapabilityWithWarning(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $this->capabilityRegistry->method('has')->with('unknown_capability')->willReturn(false);

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(static::stringContains('unknown_capability'));

        $model = $this->makeModel();
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter());

        $result = $this->makeSut()->getTools($source, ['unknown_capability'], []);

        static::assertSame([], $result);
    }

    public function testItSkipsProviderToolWhenAdapterReturnsNull(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $this->capabilityRegistry->method('has')->willReturn(true);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::NATIVE]);
        $model = $this->makeModel(capabilities: $capabilities);
        $source = $this->makeParameterSource(model: $model);

        // Adapter has no native tool for this capability
        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: null));

        $result = $this->makeSut()->getTools($source, ['web_search'], []);

        static::assertSame([], $result);
    }

    // =========================================================================
    // getTools – explicitly requested tools
    // =========================================================================

    public function testItAddsExplicitlyRequestedToolLinkedToModel(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('my_tool', capability: null, effectiveCapability: null);
        $neuronTool = $this->createMock(NeuronToolInterface::class);

        $this->capabilityRegistry->method('has')->willReturn(true);
        $this->toolConverter->method('convert')->with($aiTool)->willReturn($neuronTool);

        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(tools: $tools);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter());

        $result = $this->makeSut()->getTools($source, [], ['my_tool']);

        static::assertSame([$neuronTool], $result);
    }

    public function testItLogsWarningWhenRequestedToolIsNotLinkedToModel(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $this->capabilityRegistry->method('has')->willReturn(true);

        $model = $this->makeModel(tools: new AiToolCollection());
        $model->method('__get')->willReturnMap([
            ['capabilities', $this->makeCapabilities([])],
            ['tools', new AiToolCollection()],
            ['model_id', 'gpt-4o'],
            ['id', 1],
        ]);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter());

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(static::stringContains('missing_tool'));

        $result = $this->makeSut()->getTools($source, [], ['missing_tool']);

        static::assertSame([], $result);
    }

    public function testItSkipsRequestedToolWhenCapabilityIsDisabled(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('ws_tool', capability: 'web_search');
        $this->capabilityRegistry->method('has')->willReturn(true);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::NO]);
        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(capabilities: $capabilities, tools: $tools);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter());
        $this->toolConverter->expects(static::never())->method('convert');

        $result = $this->makeSut()->getTools($source, ['web_search'], ['ws_tool']);

        static::assertSame([], $result);
    }

    public function testItSkipsRequestedToolWhenCapabilityIsNative(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('ws_tool', capability: 'web_search');
        $providerTool = $this->createMock(ProviderTool::class);
        $this->capabilityRegistry->method('has')->willReturn(true);

        $capabilities = $this->makeCapabilities(['web_search' => ModelCapabilityValueType::NATIVE]);
        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(capabilities: $capabilities, tools: $tools);
        $source = $this->makeParameterSource(model: $model);

        $this->adapterRegistry->method('getForProvider')
            ->willReturn($this->makeAdapter(providerTool: $providerTool));
        $this->toolConverter->expects(static::never())->method('convert');

        $result = $this->makeSut()->getTools($source, ['web_search'], ['ws_tool']);

        // Only the native provider tool, NOT the custom tool
        static::assertSame([$providerTool], $result);
    }

    // =========================================================================
    // getTools – ProviderToolInterface pass-through
    // =========================================================================

    public function testItPassesThroughProviderToolInterfaceInstancesFromConverter(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        // If converter somehow returns a ProviderToolInterface, it still ends up in the list
        $providerTool = $this->createMock(ProviderTool::class);
        $aiTool = $this->makeAiTool('some_tool');
        $this->capabilityRegistry->method('has')->willReturn(true);
        $this->toolConverter->method('convert')->willReturn($providerTool);

        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(tools: $tools);
        $source = $this->makeParameterSource(model: $model);
        $this->adapterRegistry->method('getForProvider')->willReturn($this->makeAdapter());

        $result = $this->makeSut()->getTools($source, [], ['some_tool']);

        static::assertContains($providerTool, $result);
    }

    // =========================================================================
    // getTools – conversion errors
    // =========================================================================

    public function testItLogsErrorAndSkipsToolWhenConversionFails(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $aiTool = $this->makeAiTool('broken_tool');
        $this->capabilityRegistry->method('has')->willReturn(true);
        $this->toolConverter->method('convert')
            ->willThrowException(new \RuntimeException('conversion failed'));

        $tools = new AiToolCollection([$aiTool]);
        $model = $this->makeModel(tools: $tools);
        $source = $this->makeParameterSource(model: $model);
        $this->adapterRegistry->method('getForProvider')->willReturn($this->makeAdapter());

        $this->logger->expects(static::once())
            ->method('error')
            ->with(static::stringContains('broken_tool'));

        $result = $this->makeSut()->getTools($source, [], ['broken_tool']);

        static::assertSame([], $result);
    }

    // =========================================================================
    // getTools – ToolsResolvedFilterEvent
    // =========================================================================

    public function testItDispatchesToolsResolvedFilterEvent(): void
    {
        Event::fake([ToolsResolvedFilterEvent::class]);

        $model = $this->makeModel();
        $source = $this->makeParameterSource(model: $model);
        $this->adapterRegistry->method('getForProvider')->willReturn($this->makeAdapter());
        $this->capabilityRegistry->method('has')->willReturn(false);

        $this->makeSut()->getTools($source, [], []);

        Event::assertDispatched(ToolsResolvedFilterEvent::class);
    }

    public function testItReturnsToolsModifiedByFilterEvent(): void
    {
        $injectedTool = $this->createMock(ProviderTool::class);

        // Register a listener that injects an extra tool
        Event::listen(ToolsResolvedFilterEvent::class, function (ToolsResolvedFilterEvent $event) use ($injectedTool) {
            $event->setTools([$injectedTool]);
        });

        $model = $this->makeModel();
        $source = $this->makeParameterSource(model: $model);
        $this->adapterRegistry->method('getForProvider')->willReturn($this->makeAdapter());
        $this->capabilityRegistry->method('has')->willReturn(false);

        $result = $this->makeSut()->getTools($source, [], []);

        static::assertSame([$injectedTool], $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(): NeuronToolProvider
    {
        return new NeuronToolProvider(
            $this->adapterRegistry,
            $this->capabilityRegistry,
            $this->toolConverter,
            $this->logger
        );
    }

    private function makeParameterSource(AiModel $model = null): ParameterSource&MockObject
    {
        $source = $this->createMock(ParameterSource::class);
        $provider = $this->createMock(AiProvider::class);
        $source->method('getModel')->willReturn($model ?? $this->makeModel());
        $source->method('getProvider')->willReturn($provider);
        return $source;
    }

    private function makeModel(
        ModelCapabilities $capabilities = null,
        AiToolCollection  $tools = null
    ): AiModel&MockObject
    {
        $model = $this->createMock(AiModel::class);
        $model->method('__get')->willReturnMap([
            ['capabilities', $capabilities ?? $this->makeCapabilities([])],
            ['tools', $tools ?? new AiToolCollection()],
            ['id', 1],
            ['model_id', 'test-model'],
        ]);
        return $model;
    }

    private function makeCapabilities(array $capabilityMap): ModelCapabilities&MockObject
    {
        $capabilities = $this->createMock(ModelCapabilities::class);
        $capabilities->method('get')->willReturnCallback(
            fn(string $key) => $capabilityMap[$key] ?? null
        );
        return $capabilities;
    }

    private function makeAiTool(
        string      $name,
        string|null $capability = null,
        string|null $effectiveCapability = null
    ): AiTool&MockObject
    {
        $tool = $this->createMock(AiTool::class);
        $tool->method('__get')->willReturnMap([
            ['name', $name],
            ['capability', $capability],
        ]);
        $tool->method('getEffectiveCapability')->willReturn($effectiveCapability ?? $capability);
        return $tool;
    }

    private function makeAdapter(ProviderTool|null $providerTool = null): ProviderAdapterInterface&MockObject
    {
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $adapter->method('getProviderToolForCapability')->willReturn($providerTool);
        return $adapter;
    }
}
