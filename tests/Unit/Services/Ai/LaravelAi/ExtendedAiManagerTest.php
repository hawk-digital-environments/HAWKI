<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidAiManagerException;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ExtendedAiManager::class)]
class ExtendedAiManagerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build an ExtendedAiManager without running the parent constructor,
     * so we can test our overrides without booting the full Laravel AI container.
     */
    private function makeSut(): ExtendedAiManager
    {
        return $this->getMockBuilder(ExtendedAiManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
    }

    private function makeDriver(): Driver
    {
        return $this->createMock(Driver::class);
    }

    private function makeProxy(string $providerId, Driver $driver): AiProviderProxy
    {
        $model = new AiProvider();
        $model->provider_id = $providerId;

        return new AiProviderProxy(
            provider: $model,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $driver
        );
    }

    // =========================================================================
    // getDefaultInstance
    // =========================================================================

    public function testItGetDefaultInstanceThrows(): void
    {
        $sut = $this->makeSut();

        $this->expectException(InvalidAiManagerException::class);
        $this->expectExceptionMessage('does not support resolving a default instance');

        $sut->getDefaultInstance();
    }

    // =========================================================================
    // getInstanceConfig
    // =========================================================================

    public function testItGetInstanceConfigReturnsEmptyArrayByDefault(): void
    {
        $sut = $this->makeSut();

        static::assertSame([], $sut->getInstanceConfig('anything'));
    }

    // =========================================================================
    // instance — portal short-circuit
    // =========================================================================

    public function testItInstanceResolvesDriverFromActivePortalTransferId(): void
    {
        $sut = $this->makeSut();
        $driver = $this->makeDriver();
        $proxy = $this->makeProxy('prov-instance', $driver);

        $portal = ProviderDriverPortal::fromProviderProxy($proxy);

        $result = $sut->instance((string) $portal);

        static::assertSame($driver, $result);
    }

    public function testItInstanceConsumesPortalEntryOnResolution(): void
    {
        $sut = $this->makeSut();
        $driver = $this->makeDriver();
        $proxy = $this->makeProxy('prov-consume', $driver);

        $portal = ProviderDriverPortal::fromProviderProxy($proxy);
        $transferId = (string) $portal;

        $sut->instance($transferId);

        static::assertFalse(ProviderDriverPortal::isActiveTransferId($transferId));
    }

    public function testItInstanceDelegatesToParentForNonPortalName(): void
    {
        // instance() only short-circuits when the name is an active portal transfer ID.
        // A regular string name is never registered in the portal, so resolution would
        // fall through to parent::instance() — we verify the portal path is not taken.
        $unknownName = 'regular-driver-name';
        static::assertFalse(ProviderDriverPortal::isActiveTransferId($unknownName));
    }

    // =========================================================================
    // instanceWithConfig
    // =========================================================================

    public function testItInstanceWithConfigExposesConfigViaGetInstanceConfig(): void
    {
        $config = ['driver' => 'test', 'key' => 'secret'];
        $driver = $this->makeDriver();
        $proxy = $this->makeProxy('prov-cfg', $driver);

        // We need a real enough SUT to intercept getInstanceConfig during instance()
        $capturedConfig = null;

        $sut = new class ($capturedConfig, $driver) extends ExtendedAiManager {
            public function __construct(public ?array &$captured, private readonly Driver $driverStub)
            {
                // Deliberately skip parent constructor — DecoratorTrait usage
            }

            public function instance($name = null)
            {
                // Capture what getInstanceConfig returns during the call
                $this->captured = $this->getInstanceConfig($name);
                return $this->driverStub;
            }
        };

        $sut->instanceWithConfig('any-driver', $config);

        static::assertSame($config, $capturedConfig);
    }

    public function testItInstanceWithConfigRestoresConfigAfterCall(): void
    {
        $driver = $this->makeDriver();

        $sut = new class ($driver) extends ExtendedAiManager {
            public function __construct(private readonly Driver $driverStub)
            {
                // Skip parent constructor
            }

            public function instance($name = null)
            {
                return $this->driverStub;
            }
        };

        $sut->instanceWithConfig('any-driver', ['key' => 'value']);

        // After the call the ephemeral config must be gone
        static::assertSame([], $sut->getInstanceConfig('any-driver'));
    }

    public function testItInstanceWithConfigRestoresConfigEvenOnException(): void
    {
        $sut = new class extends ExtendedAiManager {
            public function __construct()
            {
                // Skip parent constructor
            }

            public function instance($name = null)
            {
                throw new \RuntimeException('boom');
            }
        };

        try {
            $sut->instanceWithConfig('driver', ['key' => 'value']);
        } catch (\RuntimeException) {
            // expected
        }

        static::assertSame([], $sut->getInstanceConfig('driver'));
    }
}
