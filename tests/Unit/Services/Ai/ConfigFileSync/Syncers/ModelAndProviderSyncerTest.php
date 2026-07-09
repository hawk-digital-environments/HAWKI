<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\ConfigFileSync\Syncers;

use App\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncer;
use App\Services\Ai\ModelInformation\ModelInfoFetcher;
use App\Services\Ai\Models\Repositories\AiModelDescriptionRepository;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Ai\Models\Repositories\AiModelUsageRuleRepository;
use App\Services\Ai\Providers\AiProviderProxyResolver;
use App\Services\Ai\Providers\Repositories\AiProviderRepository;
use App\Utils\JobMetrics;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\NullLogger;
use Tests\TestCase;
use Tests\Unit\Services\Ai\ConfigFileSync\Syncers\ModelAndProviderSyncerTestFixtures\ExposedModelAndProviderSyncer;

#[CoversClass(ModelAndProviderSyncer::class)]
class ModelAndProviderSyncerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeSut(array $providers = []): ModelAndProviderSyncer
    {
        return new ModelAndProviderSyncer(
            providers: $providers,
            modelRepository: $this->createMock(AiModelRepository::class),
            modelDescriptionRepository: $this->createMock(AiModelDescriptionRepository::class),
            providerRepository: $this->createMock(AiProviderRepository::class),
            providerProxyResolver: $this->createMock(AiProviderProxyResolver::class),
            useRuleRepository: $this->createMock(AiModelUsageRuleRepository::class),
            modelInfoFetcher: $this->createMock(ModelInfoFetcher::class),
        );
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(ModelAndProviderSyncer::class, $sut);
    }

    // =========================================================================
    // getCurrentHash
    // =========================================================================

    public function testItGetCurrentHashReturnsMd5OfJsonEncodedProviders(): void
    {
        $providers = ['openAi' => ['active' => true]];
        $sut = $this->makeSut($providers);

        static::assertSame(md5(json_encode($providers)), $sut->getCurrentHash());
    }

    public function testItGetCurrentHashChangesWhenProvidersChange(): void
    {
        $hashA = $this->makeSut(['openAi' => ['active' => true]])->getCurrentHash();
        $hashB = $this->makeSut(['openAi' => ['active' => false]])->getCurrentHash();

        static::assertNotSame($hashA, $hashB);
    }

    public function testItGetCurrentHashIsStableForSameProviders(): void
    {
        $providers = ['openAi' => ['active' => true, 'models' => []]];
        $hashA = $this->makeSut($providers)->getCurrentHash();
        $hashB = $this->makeSut($providers)->getCurrentHash();

        static::assertSame($hashA, $hashB);
    }

    // =========================================================================
    // stripWellKnownPathSuffix (via subclass exposure)
    // =========================================================================

    public static function provideTestItStripWellKnownPathSuffixData(): iterable
    {
        yield 'strips /chat/completions suffix' => [
            'https://api.openai.com/v1/chat/completions',
            'https://api.openai.com/v1',
        ];
        yield 'strips /completions suffix' => [
            'https://api.openai.com/v1/completions',
            'https://api.openai.com/v1',
        ];
        yield 'leaves base url unchanged' => [
            'https://api.openai.com/v1',
            'https://api.openai.com/v1',
        ];
        yield 'strips suffix case-insensitively' => [
            'https://api.example.com/v1/CHAT/COMPLETIONS',
            'https://api.example.com/v1',
        ];
        yield 'does not strip unrelated path segments' => [
            'https://api.example.com/v1/generate',
            'https://api.example.com/v1/generate',
        ];
    }

    #[DataProvider('provideTestItStripWellKnownPathSuffixData')]
    public function testItStripsWellKnownPathSuffix(string $input, string $expected): void
    {
        $sut = new ExposedModelAndProviderSyncer(
            providers: [],
            modelRepository: $this->createMock(AiModelRepository::class),
            modelDescriptionRepository: $this->createMock(AiModelDescriptionRepository::class),
            providerRepository: $this->createMock(AiProviderRepository::class),
            providerProxyResolver: $this->createMock(AiProviderProxyResolver::class),
            useRuleRepository: $this->createMock(AiModelUsageRuleRepository::class),
            modelInfoFetcher: $this->createMock(ModelInfoFetcher::class),
        );

        static::assertSame($expected, $sut->exposeStripSuffix($input));
    }

    // =========================================================================
    // sync — missing adapter key
    // =========================================================================

    public function testItRecordsErrorForProviderWithNoAdapterKeyAndNoBuiltInMapping(): void
    {
        $sut = $this->makeSut(['unknownProvider' => ['active' => true, 'models' => []]]);
        $metrics = new JobMetrics('test', new NullLogger());

        $sut->sync($metrics);

        static::assertTrue($metrics->hasErrors());
    }
}
