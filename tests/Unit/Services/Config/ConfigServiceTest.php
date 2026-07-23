<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Config;

use App\Services\Config\AbstractConfig;
use App\Services\Config\ConfigService;
use App\Services\System\Container\ServiceLocator;
use Illuminate\Config\Repository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Config\ConfigServiceTestFixtures\ConcreteConfig;
use Tests\Unit\Services\Config\ConfigServiceTestFixtures\ConcreteConfigWithoutMake;

#[CoversClass(ConfigService::class)]
class ConfigServiceTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a ConfigService with a ServiceLocator that has pre-registered call params for
     * ConcreteConfig::make(), bypassing the container entirely.
     */
    private function makeSutWithConcreteConfig(Repository $repo): ConfigService
    {
        $locator = new ServiceLocator();
        $locator->setCallParams(
            'configService.make.' . ConcreteConfig::class,
            [$repo]
        );
        return new ConfigService($locator, $repo);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new ConfigService(new ServiceLocator(), new Repository([]));

        static::assertInstanceOf(ConfigService::class, $sut);
    }

    // =========================================================================
    // get — validation
    // =========================================================================

    public function testItGetThrowsWhenClassDoesNotExtendAbstractConfig(): void
    {
        $sut = new ConfigService(new ServiceLocator(), new Repository([]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid config class: ' . \stdClass::class);

        $sut->get(\stdClass::class);
    }

    public function testItGetThrowsWhenClassHasNoMakeMethod(): void
    {
        $sut = new ConfigService(new ServiceLocator(), new Repository([]));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'Config class must have a static make method: ' . ConcreteConfigWithoutMake::class
        );

        $sut->get(ConcreteConfigWithoutMake::class);
    }

    // =========================================================================
    // get — happy path
    // =========================================================================

    public function testItGetReturnsAbstractConfigInstance(): void
    {
        $repo = new Repository([]);
        $sut = $this->makeSutWithConcreteConfig($repo);

        $result = $sut->get(ConcreteConfig::class);

        static::assertInstanceOf(ConcreteConfig::class, $result);
    }

    public function testItGetPassesRepoToMakeMethod(): void
    {
        $repo = new Repository(['test' => ['value' => 'injected-value']]);
        $sut = $this->makeSutWithConcreteConfig($repo);

        /** @var ConcreteConfig $result */
        $result = $sut->get(ConcreteConfig::class);

        static::assertSame('injected-value', $result->value);
    }

    public function testItGetReturnsCachedInstanceOnSubsequentCalls(): void
    {
        $repo = new Repository([]);
        $sut = $this->makeSutWithConcreteConfig($repo);

        $first = $sut->get(ConcreteConfig::class);
        $second = $sut->get(ConcreteConfig::class);

        static::assertSame($first, $second);
    }

}
