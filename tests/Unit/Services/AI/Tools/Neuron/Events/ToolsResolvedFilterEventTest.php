<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Tools\Neuron\Events;

use App\Services\Ai\Tools\Neuron\Events\ToolsResolvedFilterEvent;
use App\Services\Ai\Values\ParameterSource;
use NeuronAI\Tools\ProviderTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(ToolsResolvedFilterEvent::class)]
class ToolsResolvedFilterEventTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $event = new ToolsResolvedFilterEvent(
            [],
            $this->makeParameterSource(),
            [],
            []
        );

        static::assertInstanceOf(ToolsResolvedFilterEvent::class, $event);
    }

    // =========================================================================
    // getTools / setTools
    // =========================================================================

    public function testItReturnsInitialTools(): void
    {
        $tool = $this->createMock(ProviderTool::class);
        $event = new ToolsResolvedFilterEvent([$tool], $this->makeParameterSource(), [], []);

        static::assertSame([$tool], $event->getTools());
    }

    public function testItReturnsEmptyArrayWhenNoToolsProvided(): void
    {
        $event = new ToolsResolvedFilterEvent([], $this->makeParameterSource(), [], []);

        static::assertSame([], $event->getTools());
    }

    public function testItAllowsReplacingTools(): void
    {
        $original = $this->createMock(ProviderTool::class);
        $replacement = $this->createMock(ProviderTool::class);

        $event = new ToolsResolvedFilterEvent([$original], $this->makeParameterSource(), [], []);
        $event->setTools([$replacement]);

        static::assertSame([$replacement], $event->getTools());
    }

    public function testItAllowsAddingToolsViaSetTools(): void
    {
        $first = $this->createMock(ProviderTool::class);
        $second = $this->createMock(ProviderTool::class);

        $event = new ToolsResolvedFilterEvent([$first], $this->makeParameterSource(), [], []);
        $event->setTools([$first, $second]);

        static::assertCount(2, $event->getTools());
    }

    public function testItAllowsClearingToolsViaSetTools(): void
    {
        $tool = $this->createMock(ProviderTool::class);
        $event = new ToolsResolvedFilterEvent([$tool], $this->makeParameterSource(), [], []);
        $event->setTools([]);

        static::assertSame([], $event->getTools());
    }

    // =========================================================================
    // Read-only context getters
    // =========================================================================

    public function testItExposesParameterSource(): void
    {
        $source = $this->makeParameterSource();
        $event = new ToolsResolvedFilterEvent([], $source, [], []);

        static::assertSame($source, $event->getParameterSource());
    }

    public function testItExposesRequestedCapabilities(): void
    {
        $capabilities = ['web_search', 'knowledge_base'];
        $event = new ToolsResolvedFilterEvent([], $this->makeParameterSource(), $capabilities, []);

        static::assertSame($capabilities, $event->getRequestedCapabilities());
    }

    public function testItExposesRequestedTools(): void
    {
        $tools = ['my_tool', 'other_tool'];
        $event = new ToolsResolvedFilterEvent([], $this->makeParameterSource(), [], $tools);

        static::assertSame($tools, $event->getRequestedTools());
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeParameterSource(): ParameterSource&MockObject
    {
        return $this->createMock(ParameterSource::class);
    }
}
