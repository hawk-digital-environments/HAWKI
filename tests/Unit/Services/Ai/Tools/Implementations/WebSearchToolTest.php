<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Tools\Implementations;

use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Tools\Implementations\WebSearchTool;
use App\Services\WebSearch\Contracts\WebSearchInterface;
use App\Services\WebSearch\Exceptions\WebSearchFailedException;
use App\Services\WebSearch\Values\WebSearchResponse;
use App\Services\WebSearch\Values\WebSearchResult;
use Laravel\Ai\Tools\Request;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(WebSearchTool::class)]
class WebSearchToolTest extends TestCase
{
    private function bindSearch(WebSearchInterface $search): void
    {
        $this->app->instance(WebSearchInterface::class, $search);
    }

    public function testItDeclaresTheWebSearchCapability(): void
    {
        static::assertSame(WellKnownCapabilities::WEB_SEARCH, (new WebSearchTool())->capability());
        static::assertSame(5, (new WebSearchTool())->getMaxRuns());
    }

    public function testItPassesTheArgumentsToTheSearchAndReturnsItsResults(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->expects($this->once())
            ->method('search')
            ->with('hawki', 3)
            ->willReturn(new WebSearchResponse('hawki', [new WebSearchResult('T', 'https://a.test', 'C')], 'Answer'));
        $this->bindSearch($search);

        $result = json_decode((string)(new WebSearchTool())->handle(new Request(['query' => 'hawki', 'max_results' => 3])), true);

        static::assertSame([
            'query' => 'hawki',
            'answer' => 'Answer',
            'results' => [['title' => 'T', 'url' => 'https://a.test', 'content' => 'C']],
        ], $result['response']);
        static::assertStringContainsString('cite the sources', $result['instruction']);
    }

    public function testItDefaultsToFiveResults(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->expects($this->once())
            ->method('search')
            ->with('hawki', 5)
            ->willReturn(new WebSearchResponse('hawki', []));
        $this->bindSearch($search);

        (new WebSearchTool())->handle(new Request(['query' => 'hawki']));
    }

    public function testItTellsTheModelWhenNothingWasFound(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->method('search')->willReturn(new WebSearchResponse('hawki', []));
        $this->bindSearch($search);

        $result = json_decode((string)(new WebSearchTool())->handle(new Request(['query' => 'hawki'])), true);

        static::assertSame([], $result['response']['results']);
        static::assertStringContainsString('no results', $result['instruction']);
    }

    public function testItRejectsCallsWithoutAQuery(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->expects($this->never())->method('search');
        $this->bindSearch($search);

        $result = (string)(new WebSearchTool())->handle(new Request(['max_results' => 3]));

        static::assertStringStartsWith('[ERROR]', $result);
        static::assertStringContainsString('query', $result);
    }

    public function testItTurnsSearchFailuresIntoAnErrorForTheModel(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->method('search')->willThrowException(WebSearchFailedException::missingConfiguration('Tavily'));
        $this->bindSearch($search);

        $result = (string)(new WebSearchTool())->handle(new Request(['query' => 'hawki']));

        static::assertStringStartsWith('[ERROR]', $result);
        static::assertStringContainsString('Tavily web search configuration is missing.', $result);
    }

    public function testItStopsAfterFiveRuns(): void
    {
        $search = $this->createMock(WebSearchInterface::class);
        $search->expects($this->exactly(5))->method('search')->willReturn(new WebSearchResponse('hawki', []));
        $this->bindSearch($search);

        $sut = new WebSearchTool();
        for ($i = 0; $i < 5; $i++) {
            $sut->handle(new Request(['query' => 'hawki']));
        }

        static::assertStringContainsString('maximum number of runs (5)', (string)$sut->handle(new Request(['query' => 'hawki'])));
    }
}
