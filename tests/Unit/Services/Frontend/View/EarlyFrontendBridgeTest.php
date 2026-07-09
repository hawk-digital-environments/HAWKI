<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Frontend\View;

use App\Services\Frontend\View\EarlyFrontendBridge;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(EarlyFrontendBridge::class)]
class EarlyFrontendBridgeTest extends TestCase
{
    // =========================================================================
    // render
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertInstanceOf(EarlyFrontendBridge::class, $sut);
    }

    public function testItReturnsAString(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertIsString($sut->render());
    }

    public function testItRendersAScriptTag(): void
    {
        $sut = new EarlyFrontendBridge();
        $output = $sut->render();
        static::assertStringContainsString('<script>', $output);
        static::assertStringContainsString('</script>', $output);
    }

    public function testItInitialisesEarlyReadyQueue(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString('hawkiEarlyWaitUntilReadyQueue', $sut->render());
    }

    public function testItInitialisesEarlyBootstrapQueue(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString('hawkiEarlyWaitUntilBootstrapQueue', $sut->render());
    }

    public function testItDefinesWaitUntilReadyFunction(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString('waitUntilReady', $sut->render());
    }

    public function testItDefinesWaitUntilBootstrapFunction(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString('waitUntilBootstrap', $sut->render());
    }

    public function testItGuardsAgainstRedefiningWaitUntilReady(): void
    {
        // The guard prevents overwriting a real implementation already set by the bundle.
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString("typeof window.waitUntilReady !== 'function'", $sut->render());
    }

    public function testItGuardsAgainstRedefiningWaitUntilBootstrap(): void
    {
        $sut = new EarlyFrontendBridge();
        static::assertStringContainsString("typeof window.waitUntilBootstrap !== 'function'", $sut->render());
    }
}
