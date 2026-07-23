<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\AzureOpenAiAdapter;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AzureOpenAiAdapter::class)]
class AzureOpenAiAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): AzureOpenAiAdapter
    {
        return new AzureOpenAiAdapter();
    }

    private function makeProvider(null|array $attributes = null): AiProvider
    {
        if (!is_array($attributes)) {
            $attributes = [];
        }

        $attributes = array_merge([
            'api_url' => 'https://my-resource.openai.azure.com',
            'api_key' => 'azure-api-key',
            'additional_config' => [],
        ], $attributes);

        return new AiProvider($attributes);
    }

    private function makeFactory(array &$capturedConfig = [], array &$capturedDriverName = []): DriverFactory
    {
        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$capturedConfig, &$capturedDriverName) {
                $capturedConfig = $config;
                $capturedDriverName = [$driverName];
                return $this->createMock(Driver::class);
            });
        return $factory;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        static::assertInstanceOf(AzureOpenAiAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver — config assembly
    // =========================================================================

    public function testItCreateDriverPassesUrlToFactory(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider(['api_url' => 'https://my-resource.openai.azure.com']);
        $captured = [];
        $factory = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('https://my-resource.openai.azure.com', $captured['url']);
    }

    public function testItCreateDriverPassesApiKeyToFactory(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider(['api_key' => 'my-azure-key']);
        $captured = [];
        $factory = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('my-azure-key', $captured['key']);
    }

    public function testItCreateDriverUsesDefaultApiVersion(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider();
        $captured = [];
        $factory = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('2024-10-21', $captured['version']);
    }

    public function testItCreateDriverUsesConfiguredApiVersion(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider(['additional_config' => ['version' => '2025-01-01']]);
        $captured = [];
        $factory = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('2025-01-01', $captured['version']);
    }

    // =========================================================================
    // createDriver — missing URL throws
    // =========================================================================

    public function testItCreateDriverThrowsWhenApiUrlIsNull(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider([
            'api_url' => null,
            'name' => 'Azure OpenAI',
            'adapter_key' => 'azure',
        ]);

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::never())->method('make');

        $this->expectException(InvalidProviderConfigurationException::class);

        $sut->createDriver($provider, $factory);
    }

    public function testItCreateDriverThrowsWhenApiUrlIsEmpty(): void
    {
        $sut = $this->makeAdapter();
        $provider = $this->makeProvider([
            'api_url' => '',
            'name' => 'Azure OpenAI',
            'adapter_key' => 'azure',
        ]);

        $factory = $this->createMock(DriverFactory::class);
        $factory->expects(static::never())->method('make');

        $this->expectException(InvalidProviderConfigurationException::class);

        $sut->createDriver($provider, $factory);
    }

    // =========================================================================
    // Inherited defaults
    // =========================================================================

    public function testItGetNameLabelReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNameLabel());
    }

    public function testItGetNativeToolFactoryForCapabilityReturnsNull(): void
    {
        static::assertNull($this->makeAdapter()->getNativeToolFactoryForCapability('web_search'));
    }
}
