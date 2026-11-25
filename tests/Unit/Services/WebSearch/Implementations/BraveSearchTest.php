<?php
declare(strict_types=1);

namespace Tests\Unit\Services\WebSearch\Implementations;

use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Implementations\BraveSearch;
use App\Services\WebSearch\Values\WebSearchResult;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(BraveSearch::class)]
class BraveSearchTest extends TestCase
{
    private const API_URL = 'https://brave.test/search';

    private function makeSut(array $config = ['api_key' => 'secret', 'api_url' => self::API_URL]): BraveSearch
    {
        return new BraveSearch(
            Http::getFacadeRoot(),
            new ConfigRepository(['web_search' => ['services' => ['brave' => $config]]]),
        );
    }

    public function testItSendsTheQueryAndNormalizesTheResponse(): void
    {
        Http::fake([self::API_URL . '*' => Http::response([
            'web' => ['results' => [
                ['title' => '<strong>HAWKI</strong> docs', 'url' => 'https://a.test', 'description' => 'About <strong>HAWKI</strong>'],
                ['title' => 'No url'],
            ]],
        ])]);

        $response = $this->makeSut()->search('hawki', 4);

        static::assertSame('hawki', $response->query);
        static::assertNull($response->answer);
        static::assertEquals([new WebSearchResult('HAWKI docs', 'https://a.test', 'About HAWKI')], $response->results);

        Http::assertSent(static fn(HttpRequest $request) => str_starts_with($request->url(), self::API_URL)
            && $request->method() === 'GET'
            && $request->hasHeader('X-Subscription-Token', 'secret')
            && $request['q'] === 'hawki'
            && (int)$request['count'] === 4
        );
    }

    public function testItReturnsNoResultsWhenTheResponseHasNoWebSection(): void
    {
        Http::fake([self::API_URL . '*' => Http::response(['type' => 'search'])]);

        static::assertSame([], $this->makeSut()->search('hawki')->results);
    }

    public function testItThrowsWhenTheApiUrlIsMissing(): void
    {
        Http::fake();

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage('Brave web search configuration is missing.');

        try {
            $this->makeSut(['api_key' => 'secret', 'api_url' => ''])->search('hawki');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testItWrapsHttpErrorsIntoAWebSearchFailedException(): void
    {
        Http::fake([self::API_URL . '*' => Http::response('rate limited', 429)]);

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage('Brave connection error');

        $this->makeSut()->search('hawki');
    }
}
