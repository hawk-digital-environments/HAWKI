<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\View;

use App\Services\Frontend\View\CssLayers;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CssLayers::class)]
class CssLayersTest extends TestCase
{
    // =========================================================================
    // render
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new CssLayers();
        static::assertInstanceOf(CssLayers::class, $sut);
    }

    public function testItRendersAStyleTag(): void
    {
        $sut = new CssLayers();
        $output = $sut->render();
        static::assertStringContainsString('<style>', $output);
        static::assertStringContainsString('</style>', $output);
    }

    public function testItDeclaresAtLayerRule(): void
    {
        $sut = new CssLayers();
        static::assertStringContainsString('@layer', $sut->render());
    }

    public function testItDeclaresAllExpectedLayers(): void
    {
        $sut = new CssLayers();
        $output = $sut->render();
        foreach (['reset', 'legacy', 'tokens', 'base', 'components', 'utilities'] as $layer) {
            static::assertStringContainsString($layer, $output);
        }
    }

    public function testItDeclaresLayersInCorrectOrder(): void
    {
        $sut = new CssLayers();
        $output = $sut->render();
        $expected = '@layer reset, legacy, tokens, base, components, utilities;';
        static::assertStringContainsString($expected, $output);
    }

    public function testItReturnsAString(): void
    {
        $sut = new CssLayers();
        static::assertIsString($sut->render());
    }
}
