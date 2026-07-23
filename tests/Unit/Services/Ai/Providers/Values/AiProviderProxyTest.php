<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Values;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(AiProviderProxy::class)]
class AiProviderProxyTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeProvider(): AiProvider
    {
        $provider = new AiProvider();
        $provider->provider_id = 'openAi';
        $provider->name = 'OpenAI';
        return $provider;
    }

    private function makeSut(?AiProvider $provider = null): AiProviderProxy
    {
        $provider ??= $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $driver = $this->createMock(Driver::class);

        return new AiProviderProxy($provider, $adapter, $driver);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(AiProviderProxy::class, $sut);
    }

    // =========================================================================
    // getRealProvider
    // =========================================================================

    public function testItGetRealProviderReturnsWrappedModel(): void
    {
        $provider = $this->makeProvider();
        $sut = $this->makeSut($provider);

        static::assertSame($provider, $sut->getRealProvider());
    }

    // =========================================================================
    // Public properties
    // =========================================================================

    public function testItExposesAdapter(): void
    {
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $driver = $this->createMock(Driver::class);

        $sut = new AiProviderProxy($provider, $adapter, $driver);

        static::assertSame($adapter, $sut->adapter);
    }

    public function testItExposesDriver(): void
    {
        $provider = $this->makeProvider();
        $adapter = $this->createMock(ProviderAdapterInterface::class);
        $driver = $this->createMock(Driver::class);

        $sut = new AiProviderProxy($provider, $adapter, $driver);

        static::assertSame($driver, $sut->driver);
    }

    // =========================================================================
    // Property forwarding (__get / __isset / __unset)
    // =========================================================================

    public function testItGetForwardsPropertyReadToProvider(): void
    {
        $provider = $this->makeProvider();
        $sut = $this->makeSut($provider);

        // 'name' is a fillable attribute set on the provider
        static::assertSame('OpenAI', $sut->name);
    }

    public function testItIssetReturnsTrueForExistingProviderAttribute(): void
    {
        $provider = $this->makeProvider();
        $sut = $this->makeSut($provider);

        static::assertTrue(isset($sut->name));
    }

    public function testItIssetReturnsFalseForMissingProviderAttribute(): void
    {
        $provider = $this->makeProvider();
        $sut = $this->makeSut($provider);

        static::assertFalse(isset($sut->non_existent_attribute));
    }

    public function testItUnsetRemovesAttributeFromProvider(): void
    {
        $provider = $this->makeProvider();
        $sut = $this->makeSut($provider);

        unset($sut->name);

        static::assertFalse(isset($sut->name));
    }
}
