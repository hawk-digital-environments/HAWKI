<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Container;

use App\Services\System\Container\Exceptions\ServiceLocatorException;
use App\Services\System\Container\ServiceLocatorTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\System\Container\ServiceLocatorTraitTestFixtures\SampleService;
use Tests\Unit\Services\System\Container\ServiceLocatorTraitTestFixtures\ServiceLocatorProxy;

#[CoversTrait(ServiceLocatorTrait::class)]
class ServiceLocatorTraitTest extends TestCase
{
    // =========================================================================
    // Local service resolution
    // =========================================================================

    public function testItReturnsLocalServiceWhenSet(): void
    {
        $service = new SampleService();
        $sut = new ServiceLocatorProxy();
        $sut->setService(SampleService::class, $service);

        static::assertSame($service, $sut->resolveService(SampleService::class));
    }

    public function testItPrefersLocalServiceOverContainer(): void
    {
        $localService = new SampleService();
        $containerService = new SampleService();
        $this->app->instance(SampleService::class, $containerService);

        $sut = new ServiceLocatorProxy();
        $sut->setService(SampleService::class, $localService);

        static::assertSame($localService, $sut->resolveService(SampleService::class));
    }

    public function testItSetServiceReturnsSelf(): void
    {
        $sut = new ServiceLocatorProxy();

        static::assertSame($sut, $sut->setService(SampleService::class, new SampleService()));
    }

    // =========================================================================
    // Container fallback (explicit control)
    // =========================================================================

    public function testItFallsBackToContainerWhenExplicitlyEnabled(): void
    {
        $containerService = new SampleService();
        $this->app->instance(SampleService::class, $containerService);

        $sut = new ServiceLocatorProxy();
        $sut->useServiceContainerFallback(true);

        static::assertSame($containerService, $sut->resolveService(SampleService::class));
    }

    public function testItThrowsWhenContainerFallbackDisabledAndServiceNotLocal(): void
    {
        $this->app->instance(SampleService::class, new SampleService());

        $sut = new ServiceLocatorProxy();
        $sut->useServiceContainerFallback(false);

        $this->expectException(ServiceLocatorException::class);
        $this->expectExceptionMessage(sprintf(
            'Service with id "%s" not found in ServiceLocator and no container available to resolve it.',
            SampleService::class,
        ));

        $sut->resolveService(SampleService::class);
    }

    public function testItDoesNotThrowWhenContainerFallbackDisabledButServiceIsLocal(): void
    {
        $service = new SampleService();

        $sut = new ServiceLocatorProxy();
        $sut->useServiceContainerFallback(false);
        $sut->setService(SampleService::class, $service);

        static::assertSame($service, $sut->resolveService(SampleService::class));
    }

    public function testItUseServiceContainerFallbackReturnsSelf(): void
    {
        $sut = new ServiceLocatorProxy();

        static::assertSame($sut, $sut->useServiceContainerFallback(false));
    }

    // =========================================================================
    // PHPUnit auto-detection
    // =========================================================================

    public function testItAutoDetectsPhpUnitAndThrowsWhenNoLocalService(): void
    {
        // No explicit useServiceContainerFallback() call — PHPUnit auto-detection should kick in
        // and disable container fallback so the missing mock is surfaced immediately.
        $this->app->instance(SampleService::class, new SampleService());

        $sut = new ServiceLocatorProxy();

        $this->expectException(ServiceLocatorException::class);
        $this->expectExceptionMessage(sprintf(
            'Service with id "%s" not found in ServiceLocator and no container available to resolve it.',
            SampleService::class,
        ));

        $sut->resolveService(SampleService::class);
    }

    // =========================================================================
    // Reset to auto-detection
    // =========================================================================

    public function testItResetsToAutoDetectionWhenFallbackSetToNull(): void
    {
        // Enable fallback explicitly, then reset to null → auto-detection re-applies.
        // Since we are inside PHPUnit, auto-detection disables fallback and the call should throw.
        $this->app->instance(SampleService::class, new SampleService());

        $sut = new ServiceLocatorProxy();
        $sut->useServiceContainerFallback(true);
        $sut->useServiceContainerFallback(null);

        $this->expectException(ServiceLocatorException::class);

        $sut->resolveService(SampleService::class);
    }
}
