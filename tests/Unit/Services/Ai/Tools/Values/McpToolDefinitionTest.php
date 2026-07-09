<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools\Values;

use App\Services\Ai\Tools\Values\McpToolDefinition;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(McpToolDefinition::class)]
class McpToolDefinitionTest extends TestCase
{
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new McpToolDefinition(
            name: 'my_tool',
            description: 'Does something',
            config: ['inputSchema' => ['type' => 'object']],
            capability: 'web_search'
        );

        static::assertInstanceOf(McpToolDefinition::class, $sut);
    }

    // =========================================================================
    // Property access
    // =========================================================================

    public function testItExposesName(): void
    {
        $sut = new McpToolDefinition('my_tool', null, [], null);

        static::assertSame('my_tool', $sut->name);
    }

    public function testItExposesDescription(): void
    {
        $sut = new McpToolDefinition('tool', 'A description', [], null);

        static::assertSame('A description', $sut->description);
    }

    public function testItAllowsNullDescription(): void
    {
        $sut = new McpToolDefinition('tool', null, [], null);

        static::assertNull($sut->description);
    }

    public function testItExposesConfig(): void
    {
        $config = ['inputSchema' => ['type' => 'object', 'properties' => ['q' => ['type' => 'string']]]];
        $sut = new McpToolDefinition('tool', null, $config, null);

        static::assertSame($config, $sut->config);
    }

    public function testItExposesCapability(): void
    {
        $sut = new McpToolDefinition('tool', null, [], 'web_search');

        static::assertSame('web_search', $sut->capability);
    }

    public function testItAllowsNullCapability(): void
    {
        $sut = new McpToolDefinition('tool', null, [], null);

        static::assertNull($sut->capability);
    }
}
