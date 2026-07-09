<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Container;

use App\Services\System\Container\SystemEnvironment;
use Illuminate\Foundation\Application;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(SystemEnvironment::class)]
class SystemEnvironmentTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $app = $this->createMock(Application::class);
        $sut = new SystemEnvironment($app);

        static::assertInstanceOf(SystemEnvironment::class, $sut);
    }

    // =========================================================================
    // environment
    // =========================================================================

    public function testItReturnsCurrentEnvironmentName(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('environment')->with()->willReturn('local');

        $sut = new SystemEnvironment($app);

        static::assertSame('local', $sut->environment());
    }

    public function testItDelegatesToApplicationEnvironmentWithSingleArg(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('environment')
            ->with('production')
            ->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->environment('production'));
    }

    public function testItDelegatesToApplicationEnvironmentWithMultipleArgs(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())
            ->method('environment')
            ->with('local', 'testing')
            ->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->environment('local', 'testing'));
    }

    // =========================================================================
    // isLocal
    // =========================================================================

    public function testItDelegatesToApplicationIsLocal(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('isLocal')->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->isLocal());
    }

    public function testItReturnsIsLocalFalseWhenNotLocal(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('isLocal')->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->isLocal());
    }

    // =========================================================================
    // isProduction
    // =========================================================================

    public function testItDelegatesToApplicationIsProduction(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('isProduction')->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->isProduction());
    }

    public function testItReturnsIsProductionFalseWhenNotProduction(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('isProduction')->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->isProduction());
    }

    // =========================================================================
    // runningInConsole
    // =========================================================================

    public function testItDelegatesToApplicationRunningInConsole(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('runningInConsole')->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->runningInConsole());
    }

    public function testItReturnsRunningInConsoleFalseWhenInWebContext(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('runningInConsole')->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->runningInConsole());
    }

    // =========================================================================
    // runningUnitTests
    // =========================================================================

    public function testItDelegatesToApplicationRunningUnitTests(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('runningUnitTests')->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->runningUnitTests());
    }

    public function testItReturnsRunningUnitTestsFalseWhenNotTesting(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('runningUnitTests')->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->runningUnitTests());
    }

    // =========================================================================
    // hasDebugModeEnabled
    // =========================================================================

    public function testItDelegatesToApplicationHasDebugModeEnabled(): void
    {
        $app = $this->createMock(Application::class);
        $app->expects(static::once())->method('hasDebugModeEnabled')->willReturn(true);

        $sut = new SystemEnvironment($app);

        static::assertTrue($sut->hasDebugModeEnabled());
    }

    public function testItReturnsHasDebugModeEnabledFalseWhenDebugOff(): void
    {
        $app = $this->createMock(Application::class);
        $app->method('hasDebugModeEnabled')->willReturn(false);

        $sut = new SystemEnvironment($app);

        static::assertFalse($sut->hasDebugModeEnabled());
    }
}
