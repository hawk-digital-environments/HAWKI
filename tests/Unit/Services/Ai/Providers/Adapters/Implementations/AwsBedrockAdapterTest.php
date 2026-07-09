<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\AwsBedrockAdapter;
use App\Services\Ai\Providers\Values\ProviderSettings;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(AwsBedrockAdapter::class)]
class AwsBedrockAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): AwsBedrockAdapter
    {
        return new AwsBedrockAdapter();
    }

    private function makeProvider(string $apiKey, array $adapterSettings = []): AiProvider
    {
        $settings = ProviderSettings::fromArray(['adapter' => $adapterSettings]);

        $provider = $this->createMock(AiProvider::class);
        $provider->method('__get')
            ->willReturnCallback(fn(string $key) => match ($key) {
                'api_key'  => $apiKey,
                'settings' => $settings,
                default    => null,
            });

        return $provider;
    }

    private function makeFactory(array &$capturedConfig = []): DriverFactory
    {
        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config) use (&$capturedConfig) {
                $capturedConfig = $config;
                return $this->createMock(Driver::class);
            });
        return $factory;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeAdapter();
        static::assertInstanceOf(AwsBedrockAdapter::class, $sut);
    }

    // =========================================================================
    // createDriver — static IAM credentials
    // =========================================================================

    public function testItCreateDriverParsesStaticCredentials(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('AKIAIOSFODNN7EXAMPLE wJalrXUtnFEMI/K7MDENG');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('AKIAIOSFODNN7EXAMPLE', $captured['access_key_id']);
        static::assertSame('wJalrXUtnFEMI/K7MDENG', $captured['secret_access_key']);
        static::assertNull($captured['key']);
    }

    public function testItCreateDriverSetsNullKeyForStaticCredentials(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('MYACCESSKEY MYSECRETKEY');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertNull($captured['key']);
    }

    // =========================================================================
    // createDriver — bearer token
    // =========================================================================

    public function testItCreateDriverParsesTokenCredential(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('token:myBearerToken123');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('myBearerToken123', $captured['key']);
        static::assertNull($captured['access_key_id']);
        static::assertNull($captured['secret_access_key']);
    }

    // =========================================================================
    // createDriver — default region / version
    // =========================================================================

    public function testItCreateDriverUsesDefaultRegionWhenNotInAdapterSettings(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('eu-central-1', $captured['region']);
    }

    public function testItCreateDriverUsesDefaultVersionWhenNotInAdapterSettings(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET');
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('latest', $captured['version']);
    }

    public function testItCreateDriverUsesAdapterSettingsRegionWhenProvided(): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider('KEY SECRET', ['region' => 'us-west-2']);
        $captured = [];
        $factory  = $this->makeFactory($captured);

        $sut->createDriver($provider, $factory);

        static::assertSame('us-west-2', $captured['region']);
    }

    // =========================================================================
    // createDriver — invalid key format throws
    // =========================================================================

    public static function provideTestItCreateDriverThrowsForInvalidKeyFormatData(): iterable
    {
        yield 'empty string'            => [''];
        yield 'plain key no space'      => ['JUSTAKEYNOSPACEORANYTHING'];
        yield 'token prefix but spaces' => ['token:FOO BAR'];
        yield 'three space-separated'   => ['A B C'];
    }

    #[DataProvider('provideTestItCreateDriverThrowsForInvalidKeyFormatData')]
    public function testItCreateDriverThrowsForInvalidKeyFormat(string $apiKey): void
    {
        $sut      = $this->makeAdapter();
        $provider = $this->makeProvider($apiKey);
        $factory  = $this->createMock(DriverFactory::class);
        $factory->expects(static::never())->method('make');

        $this->expectException(InvalidProviderConfigurationException::class);
        $this->expectExceptionMessage('Invalid API key format for AWS Bedrock provider');

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
