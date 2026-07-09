<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters;

use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\DriverFactoryFactory;
use App\Services\System\Container\ServiceLocator;
use Laravel\Ai\AiManager;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(DriverFactoryFactory::class)]
class DriverFactoryFactoryTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new DriverFactoryFactory(
            $this->createMock(AiManager::class),
            $this->createMock(ServiceLocator::class),
        );

        static::assertInstanceOf(DriverFactoryFactory::class, $sut);
    }

    // =========================================================================
    // createFactoryForProvider
    // =========================================================================

    public function testItCreatesADriverFactoryInstance(): void
    {
        $sut = new DriverFactoryFactory(
            $this->createMock(AiManager::class),
            $this->createMock(ServiceLocator::class),
        );

        $provider = new \App\Models\Ai\AiProvider();

        $factory = $sut->createFactoryForProvider($provider);

        static::assertInstanceOf(DriverFactory::class, $factory);
    }

    public function testItCreatesADistinctFactoryForEachProvider(): void
    {
        $sut = new DriverFactoryFactory(
            $this->createMock(AiManager::class),
            $this->createMock(ServiceLocator::class),
        );

        $providerA = new \App\Models\Ai\AiProvider();
        $providerB = new \App\Models\Ai\AiProvider();

        $factoryA = $sut->createFactoryForProvider($providerA);
        $factoryB = $sut->createFactoryForProvider($providerB);

        static::assertNotSame($factoryA, $factoryB);
    }
}
