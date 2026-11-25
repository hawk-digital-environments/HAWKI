<?php
declare(strict_types=1);

namespace Tests\Unit\Services\WebSearch\Implementations;

use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Implementations\TavilySearch;
use App\Services\WebSearch\Values\WebSearchResult;
use Illuminate\Config\Repository as ConfigRepository;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TavilySearch::class)]
class TavilySearchTest extends TestCase
{
    private const API_URL = 'https://tavily.test/search';

    private function makeSut(array $config = ['api_key' => 'secret', 'api_url' => self::API_URL]): TavilySearch
    {
        return new TavilySearch(
            Http::getFacadeRoot(),
            new ConfigRepository(['web_search' => ['services' => ['tavily' => $config]]]),
        );
    }

    public function testItSendsTheQueryAndNormalizesTheResponse(): void
    {
        Http::fake([self::API_URL => Http::response([
            'answer' => 'The answer.',
            'results' => [
                ['title' => 'First', 'url' => 'https://a.test', 'content' => 'Snippet A', 'score' => 0.9],
                ['title' => 'No url', 'content' => 'skipped'],
                ['title' => 'Second', 'url' => 'https://b.test', 'content' => 'Snippet B'],
            ],
        ])]);

        $response = $this->makeSut()->search('hawki', 3);

        static::assertSame('hawki', $response->query);
        static::assertSame('The answer.', $response->answer);
        static::assertEquals([
            new WebSearchResult('First', 'https://a.test', 'Snippet A'),
            new WebSearchResult('Second', 'https://b.test', 'Snippet B'),
        ], $response->results);

        Http::assertSent(static fn(HttpRequest $request) => $request->url() === self::API_URL
            && $request->method() === 'POST'
            && $request->hasHeader('Authorization', 'Bearer secret')
            && $request['query'] === 'hawki'
            && $request['max_results'] === 3
        );
    }

    public function testItReturnsNullAnswerWhenTheProviderGivesNone(): void
    {
        Http::fake([self::API_URL => Http::response(['answer' => '', 'results' => []])]);

        $response = $this->makeSut()->search('hawki');

        static::assertNull($response->answer);
        static::assertSame([], $response->results);
    }

    public function testItThrowsWhenTheApiKeyIsMissing(): void
    {
        Http::fake();

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage('Tavily web search configuration is missing.');

        try {
            $this->makeSut(['api_key' => '', 'api_url' => self::API_URL])->search('hawki');
        } finally {
            Http::assertNothingSent();
        }
    }

    public function testItWrapsHttpErrorsIntoAWebSearchFailedException(): void
    {
        Http::fake([self::API_URL => Http::response(['detail' => 'Unauthorized'], 401)]);

        $this->expectException(WebSearchFailedException::class);
        $this->expectExceptionMessage('Tavily connection error');

        $this->makeSut()->search('hawki');
    }
}
