<?php
declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\AiServiceProvider;
use App\Services\Ai\LaravelAi\ExtendedAiManager;
use App\Services\Ai\Providers\StallAware\StallAwareOllamaGateway;
use App\Services\Ai\Providers\StallAware\StallAwareOpenAiCompatibleGateway;
use Laravel\Ai\AiManager;
use Laravel\Ai\Providers\OllamaProvider;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AiServiceProvider::class)]
class AiServiceProviderTest extends TestCase
{
    public function testItResolvesOpenAiCompatibleDriversWithTheStallAwareGateway(): void
    {
        $manager = $this->app->make(AiManager::class);
        static::assertInstanceOf(ExtendedAiManager::class, $manager);

        $provider = $manager->instanceWithConfig('openai-compatible', [
            'name' => 'hawki_test',
            'driver' => 'openai-compatible',
            'key' => 'test-key',
            'url' => 'https://api.example.com/v1',
            'stall_timeout' => 120,
        ]);

        static::assertInstanceOf(OpenAiCompatibleProvider::class, $provider);
        static::assertInstanceOf(StallAwareOpenAiCompatibleGateway::class, $provider->textGateway());
    }

    public function testItResolvesOllamaDriversWithTheStallAwareGateway(): void
    {
        $manager = $this->app->make(AiManager::class);
        static::assertInstanceOf(ExtendedAiManager::class, $manager);

        $provider = $manager->instanceWithConfig('ollama', [
            'name' => 'hawki_test',
            'driver' => 'ollama',
            'url' => 'http://localhost:11434',
            'stall_timeout' => 120,
        ]);

        static::assertInstanceOf(OllamaProvider::class, $provider);
        static::assertInstanceOf(StallAwareOllamaGateway::class, $provider->textGateway());
    }
}
