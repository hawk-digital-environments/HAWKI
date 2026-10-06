<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\Implementations;

use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\Drivers\LiteLlm\LiteLlmGateway;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Implementations\LiteLlmAdapter;
use App\Services\Ai\Providers\Adapters\Implementations\OpenAiLikeAdapter;
use Illuminate\Events\Dispatcher;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Providers\OpenAiProvider;
use Laravel\Ai\Providers\Provider as Driver;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(LiteLlmAdapter::class)]
class LiteLlmAdapterTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeAdapter(): LiteLlmAdapter
    {
        return new LiteLlmAdapter();
    }

    /**
     * @return array{0: Lab|string|null, 1: array, 2: \Closure|null}
     */
    private function captureMakeCall(AiProvider $provider): array
    {
        $captured = [null, [], null];

        $factory = $this->createMock(DriverFactory::class);
        $factory->method('make')
            ->willReturnCallback(function ($driverName, array $config, ?\Closure $builder) use (&$captured) {
                $captured = [$driverName, $config, $builder];
                return $this->createMock(Driver::class);
            });

        $this->makeAdapter()->createDriver($provider, $factory);

        return $captured;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItIsAnOpenAiLikeAdapter(): void
    {
        static::assertInstanceOf(OpenAiLikeAdapter::class, $this->makeAdapter());
    }

    // =========================================================================
    // createDriver
    // =========================================================================

    public function testItCreateDriverUsesOpenAiDriverWithProviderUrlAndKey(): void
    {
        [$driverName, $config] = $this->captureMakeCall(
            new AiProvider(['api_url' => 'https://litellm.example.org/v1', 'api_key' => 'sk-test'])
        );

        static::assertSame(Lab::OpenAI, $driverName);
        static::assertSame('https://litellm.example.org/v1', $config['url']);
        static::assertSame('sk-test', $config['key']);
    }

    public function testItCreateDriverBuilderProducesOpenAiProviderWithLiteLlmGateway(): void
    {
        [, , $builder] = $this->captureMakeCall(
            new AiProvider(['api_url' => 'https://litellm.example.org/v1', 'api_key' => 'sk-test'])
        );

        static::assertNotNull($builder);

        $driver = $builder(new Dispatcher(), ['key' => 'sk-test', 'url' => 'https://litellm.example.org/v1']);

        static::assertInstanceOf(OpenAiProvider::class, $driver);

        $gateway = (new \ReflectionProperty($driver, 'gateway'))->getValue($driver);
        static::assertInstanceOf(LiteLlmGateway::class, $gateway);
    }
}
