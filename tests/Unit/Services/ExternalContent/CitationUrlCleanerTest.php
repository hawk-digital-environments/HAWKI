<?php
declare(strict_types=1);

namespace Tests\Unit\Services\ExternalContent;

use App\Services\ExternalContent\CitationUrlCleaner;
use App\Services\ExternalContent\UrlCleaner;
use Laravel\Ai\Responses\Data\Citation;
use Laravel\Ai\Responses\Data\UrlCitation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(CitationUrlCleaner::class)]
class CitationUrlCleanerTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /** @return MockObject&UrlCleaner */
    private function makeUrlCleaner(): MockObject
    {
        return $this->createMock(UrlCleaner::class);
    }

    private function makeSut(UrlCleaner $urlCleaner = null): CitationUrlCleaner
    {
        return new CitationUrlCleaner($urlCleaner ?? $this->makeUrlCleaner());
    }

    private function makeUrlCitation(string $url, string $title = 'Test'): UrlCitation
    {
        return new UrlCitation(url: $url, title: $title);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(CitationUrlCleaner::class, $sut);
    }

    // =========================================================================
    // clean — single citation
    // =========================================================================

    public function testItCleanPassesNonUrlCitationThrough(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->expects(static::never())->method('clean');

        $sut = $this->makeSut($urlCleaner);
        $citation = $this->createMock(Citation::class);

        $result = $sut->clean($citation);

        static::assertSame($citation, $result);
    }

    public function testItCleanCleansUrlCitationUrl(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->method('clean')
            ->with('https://redirect.example.com/')
            ->willReturn('https://final.example.com/');

        $sut = $this->makeSut($urlCleaner);
        $citation = $this->makeUrlCitation('https://redirect.example.com/');

        $result = $sut->clean($citation);

        static::assertInstanceOf(UrlCitation::class, $result);
        static::assertSame('https://final.example.com/', $result->url);
    }

    public function testItCleanMutatesThePassedCitationDirectly(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->method('clean')->willReturn('https://final.example.com/');

        $sut = $this->makeSut($urlCleaner);
        $citation = $this->makeUrlCitation('https://original.example.com/');

        $result = $sut->clean($citation);

        // clean() mutates the original object in place (unlike cleanMany() which clones)
        static::assertSame($citation, $result);
        static::assertSame('https://final.example.com/', $citation->url);
    }

    // =========================================================================
    // cleanMany — batch processing
    // =========================================================================

    public function testItCleanManyReturnsUnchangedWhenNoCitationsAreUrlCitations(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->expects(static::never())->method('cleanMany');

        $sut = $this->makeSut($urlCleaner);
        $citation = $this->createMock(Citation::class);

        $result = $sut->cleanMany([$citation]);

        static::assertSame([$citation], $result);
    }

    public function testItCleanManyBatchCleansUrlCitationsOnly(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->expects(static::once())
            ->method('cleanMany')
            ->with(['https://short.example.com/', 'https://tracker.example.com/?utm_source=x'])
            ->willReturn(['https://final.example.com/', 'https://tracker.example.com/']);

        $sut = $this->makeSut($urlCleaner);

        $nonUrl = $this->createMock(Citation::class);
        $url1 = $this->makeUrlCitation('https://short.example.com/', 'Article 1');
        $url2 = $this->makeUrlCitation('https://tracker.example.com/?utm_source=x', 'Article 2');

        $result = $sut->cleanMany([$nonUrl, $url1, $url2]);

        static::assertCount(3, $result);
        static::assertSame($nonUrl, $result[0]);

        static::assertInstanceOf(UrlCitation::class, $result[1]);
        static::assertSame('https://final.example.com/', $result[1]->url);
        static::assertSame('Article 1', $result[1]->title);

        static::assertInstanceOf(UrlCitation::class, $result[2]);
        static::assertSame('https://tracker.example.com/', $result[2]->url);
        static::assertSame('Article 2', $result[2]->title);
    }

    public function testItCleanManyReturnsClonedCitationsNotOriginals(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->method('cleanMany')->willReturn(['https://final.example.com/']);

        $sut = $this->makeSut($urlCleaner);
        $original = $this->makeUrlCitation('https://original.example.com/');

        $result = $sut->cleanMany([$original]);

        // cleanMany() clones citations instead of mutating them
        static::assertNotSame($original, $result[0]);
        static::assertSame('https://original.example.com/', $original->url);
        static::assertSame('https://final.example.com/', $result[0]->url);
    }

    public function testItCleanManyPreservesInputArrayIndices(): void
    {
        $urlCleaner = $this->makeUrlCleaner();
        $urlCleaner->method('cleanMany')->willReturn(['https://final.example.com/']);

        $sut = $this->makeSut($urlCleaner);
        $nonUrl = $this->createMock(Citation::class);
        $urlCit = $this->makeUrlCitation('https://original.example.com/');

        // Input: index 0 = non-URL, index 1 = URL citation
        $result = $sut->cleanMany([$nonUrl, $urlCit]);

        static::assertArrayHasKey(0, $result);
        static::assertArrayHasKey(1, $result);
        static::assertSame($nonUrl, $result[0]);
        static::assertSame('https://final.example.com/', $result[1]->url);
    }
}
