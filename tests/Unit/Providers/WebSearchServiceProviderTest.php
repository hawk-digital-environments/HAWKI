<?php
declare(strict_types=1);

namespace Tests\Unit\Providers;

use App\Providers\WebSearchServiceProvider;
use App\Services\WebSearch\Contracts\WebSearchInterface;
use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Implementations\BraveSearch;
use App\Services\WebSearch\Implementations\TavilySearch;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(WebSearchServiceProvider::class)]
class WebSearchServiceProviderTest extends TestCase
{
    #[DataProvider('provideConfiguredProviders')]
    public function testItBindsTheConfiguredProvider(string $provider, string $expectedClass): void
    {
        config(['web_search.default' => $provider]);

        static::assertInstanceOf($expectedClass, $this->app->make(WebSearchInterface::class));
    }

    public static function provideConfiguredProviders(): iterable
    {
        yield 'brave' => ['brave', BraveSearch::class];
        yield 'tavily' => ['tavily', TavilySearch::class];
        yield 'surrounding whitespace is ignored' => [' tavily ', TavilySearch::class];
    }

    public function testItThrowsWhenNoProviderIsConfigured(): void
    {
        config(['web_search.default' => '']);

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage('No web search provider is configured');

        $this->app->make(WebSearchInterface::class);
    }

    public function testItThrowsForAnUnknownProvider(): void
    {
        config(['web_search.default' => 'bing']);

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage("Invalid default web search provider 'bing'.");

        $this->app->make(WebSearchInterface::class);
    }
}
