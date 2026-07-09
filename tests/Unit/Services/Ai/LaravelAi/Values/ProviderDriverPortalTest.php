<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi\Values;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidTransferIdException;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ProviderDriverPortal::class)]
class ProviderDriverPortalTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeDriver(): Driver
    {
        return $this->createMock(Driver::class);
    }

    private function makeProxy(string $providerId = 'test-provider'): AiProviderProxy
    {
        $model = new AiProvider();
        $model->provider_id = $providerId;

        return new AiProviderProxy(
            provider: $model,
            adapter: $this->createMock(ProviderAdapterInterface::class),
            driver: $this->makeDriver()
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $driver = $this->makeDriver();
        $sut = new ProviderDriverPortal('some-id', $driver);
        static::assertInstanceOf(ProviderDriverPortal::class, $sut);
    }

    // =========================================================================
    // __toString
    // =========================================================================

    public function testItCastsToTransferId(): void
    {
        $driver = $this->makeDriver();
        $sut = new ProviderDriverPortal('my-transfer-id', $driver);
        static::assertSame('my-transfer-id', (string) $sut);
    }

    // =========================================================================
    // fromProviderProxy / isActiveTransferId / fromTransferId
    // =========================================================================

    public function testItRegistersTransferIdOnFromProviderProxy(): void
    {
        $proxy = $this->makeProxy('prov-1');
        $portal = ProviderDriverPortal::fromProviderProxy($proxy);

        static::assertTrue(ProviderDriverPortal::isActiveTransferId((string) $portal));
    }

    public function testItBuildsDeterministicTransferId(): void
    {
        $proxy = $this->makeProxy('prov-abc');
        $portal = ProviderDriverPortal::fromProviderProxy($proxy);

        static::assertSame('provider-adapter-portal:transfer:prov-abc', (string) $portal);
        // Consume so cleanup does not affect other tests
        ProviderDriverPortal::fromTransferId((string) $portal);
    }

    public function testItExposesTheSameDriverFromProxy(): void
    {
        $proxy = $this->makeProxy('prov-2');
        $portal = ProviderDriverPortal::fromProviderProxy($proxy);
        $retrieved = ProviderDriverPortal::fromTransferId((string) $portal);

        static::assertSame($proxy->driver, $retrieved->driver);
    }

    public function testItConsumesEntryOnFromTransferId(): void
    {
        $proxy = $this->makeProxy('prov-3');
        $portal = ProviderDriverPortal::fromProviderProxy($proxy);
        $id = (string) $portal;

        ProviderDriverPortal::fromTransferId($id);

        static::assertFalse(ProviderDriverPortal::isActiveTransferId($id));
    }

    public function testItThrowsForUnknownTransferId(): void
    {
        $this->expectException(InvalidTransferIdException::class);
        $this->expectExceptionMessage('No active transfer found for transfer ID "does-not-exist"');

        ProviderDriverPortal::fromTransferId('does-not-exist');
    }

    public function testItThrowsForAlreadyConsumedTransferId(): void
    {
        $proxy = $this->makeProxy('prov-consumed');
        $portal = ProviderDriverPortal::fromProviderProxy($proxy);
        $id = (string) $portal;

        ProviderDriverPortal::fromTransferId($id);

        $this->expectException(InvalidTransferIdException::class);
        ProviderDriverPortal::fromTransferId($id);
    }

    public function testItIsNotActiveTransferIdForUnknownId(): void
    {
        static::assertFalse(ProviderDriverPortal::isActiveTransferId('never-registered'));
    }
}
