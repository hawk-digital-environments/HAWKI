<?php
declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\ServiceLocatorTrait;
use Illuminate\Container\EntryNotFoundException;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Utils\ServiceLocatorTraitTestFixtures\SampleService;
use Tests\Unit\Utils\ServiceLocatorTraitTestFixtures\ServiceLocatorProxy;

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

        $result = $sut->resolveService(SampleService::class);

        static::assertSame($service, $result);
    }

    public function testItPreferLocalServiceOverContainerService(): void
    {
        $localService = new SampleService();
        $containerService = new SampleService();
        $this->app->instance(SampleService::class, $containerService);

        $sut = new ServiceLocatorProxy();
        $sut->setService(SampleService::class, $localService);

        $result = $sut->resolveService(SampleService::class);

        static::assertSame($localService, $result);
    }

    // =========================================================================
    // Container fallback
    // =========================================================================

    public function testItFallsBackToContainerWhenServiceNotSetLocally(): void
    {
        $containerService = new SampleService();
        $this->app->instance(SampleService::class, $containerService);

        $sut = new ServiceLocatorProxy();
        // Explicitly disable fail-on-missing to bypass the PHPUnit auto-detection,
        // which would otherwise throw before the container fallback is reached.
        $sut->setFailOnMissingLocalService(false);

        $result = $sut->resolveService(SampleService::class);

        static::assertSame($containerService, $result);
    }

    // =========================================================================
    // PHPUnit auto-detection
    // =========================================================================

    public function testItAutoDetectsPhpUnitAndThrowsOnSubsequentCallsWithNoLocalService(): void
    {
        // Bind to container so the first call (which triggers auto-detection) succeeds.
        $this->app->instance(SampleService::class, new SampleService());

        $sut = new ServiceLocatorProxy();
        // First call: failOnMissing is false → auto-detection runs (lines 99-107) →
        // sets failOnMissing = true → falls through to container → returns service.
        $sut->resolveService(SampleService::class);

        // Second call: failOnMissing is now true → EntryNotFoundException is thrown
        // before reaching the container, even though SampleService is still bound.
        $this->expectException(EntryNotFoundException::class);
        $this->expectExceptionMessage(SampleService::class);
        $sut->resolveService(SampleService::class);
    }

    // =========================================================================
    // Fail-on-missing behaviour
    // =========================================================================

    public function testItThrowsWhenServiceMissingAndFailOnMissingIsTrue(): void
    {
        $sut = new ServiceLocatorProxy();
        $sut->setFailOnMissingLocalService(true);

        $this->expectException(EntryNotFoundException::class);
        $this->expectExceptionMessage(SampleService::class);

        $sut->resolveService(SampleService::class);
    }

    public function testItDoesNotThrowWhenServiceSetAndFailOnMissingIsTrue(): void
    {
        $service = new SampleService();
        $sut = new ServiceLocatorProxy();
        $sut->setFailOnMissingLocalService(true);
        $sut->setService(SampleService::class, $service);

        $result = $sut->resolveService(SampleService::class);

        static::assertSame($service, $result);
    }

    // =========================================================================
    // setFailOnMissingLocalService chaining
    // =========================================================================

    public function testItReturnsSelfFromSetFailOnMissingLocalService(): void
    {
        $sut = new ServiceLocatorProxy();

        $result = $sut->setFailOnMissingLocalService(true);

        static::assertSame($sut, $result);
    }

    public function testItReturnsSelfWhenSetFailOnMissingLocalServiceCalledWithFalse(): void
    {
        $sut = new ServiceLocatorProxy();

        $result = $sut->setFailOnMissingLocalService(false);

        static::assertSame($sut, $result);
    }
}
