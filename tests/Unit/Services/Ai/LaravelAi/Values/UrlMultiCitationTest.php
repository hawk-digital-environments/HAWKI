<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\LaravelAi\Values;

use App\Services\Ai\LaravelAi\Values\UrlMultiCitation;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UrlMultiCitation::class)]
class UrlMultiCitationTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        static::assertInstanceOf(UrlMultiCitation::class, $sut);
    }

    public function testItConstructsWithoutRange(): void
    {
        $sut = new UrlMultiCitation('https://example.com', 'Example');
        static::assertSame(0, $sut->ranges->count());
    }

    public function testItConstructsWithInitialRange(): void
    {
        $sut = new UrlMultiCitation('https://example.com', 'Example', 10, 20);
        static::assertSame(1, $sut->ranges->count());
        static::assertSame([10, 20], $sut->ranges->first());
    }

    public function testItConstructsWithByteOffsetFlag(): void
    {
        $sut = new UrlMultiCitation('https://example.com', null, null, null, true);
        static::assertTrue($sut->isByteOffset);
    }

    public function testItDefaultsByteOffsetToFalse(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        static::assertFalse($sut->isByteOffset);
    }

    // =========================================================================
    // addRange
    // =========================================================================

    public function testItAddsRange(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, 15);
        static::assertSame(1, $sut->ranges->count());
        static::assertSame([5, 15], $sut->ranges->first());
    }

    public function testItIgnoresNullStartIndex(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(null, 15);
        static::assertSame(0, $sut->ranges->count());
    }

    public function testItIgnoresNullEndIndex(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, null);
        static::assertSame(0, $sut->ranges->count());
    }

    public function testItIgnoresDuplicateRange(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, 15);
        $sut->addRange(5, 15);
        static::assertSame(1, $sut->ranges->count());
    }

    public function testItAcceptsDistinctRanges(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, 15);
        $sut->addRange(20, 30);
        static::assertSame(2, $sut->ranges->count());
    }

    public function testItSetsParentStartAndEndIndexFromFirstAddedRange(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, 15);
        $sut->addRange(20, 30);
        // Parent fields must reflect the first range only
        static::assertSame(5, $sut->startIndex);
        static::assertSame(15, $sut->endIndex);
    }

    public function testItDoesNotOverwriteParentIndexOnSubsequentRanges(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(5, 15);
        $sut->addRange(1, 3);
        static::assertSame(5, $sut->startIndex);
        static::assertSame(15, $sut->endIndex);
    }

    public function testItPreservesConstructorRangeWhenAddingMore(): void
    {
        $sut = new UrlMultiCitation('https://example.com', null, 0, 5);
        $sut->addRange(10, 20);
        static::assertSame(2, $sut->ranges->count());
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItToArrayIncludesParentFields(): void
    {
        $sut = new UrlMultiCitation('https://example.com', 'Title', 0, 10);
        $result = $sut->toArray();
        static::assertSame('https://example.com', $result['url']);
        static::assertSame('Title', $result['title']);
    }

    public function testItToArrayIncludesRanges(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $sut->addRange(0, 5);
        $sut->addRange(10, 15);
        $result = $sut->toArray();
        static::assertArrayHasKey('ranges', $result);
        static::assertCount(2, $result['ranges']);
    }

    public function testItToArrayIncludesByteOffsetFlag(): void
    {
        $sut = new UrlMultiCitation('https://example.com', null, null, null, true);
        $result = $sut->toArray();
        static::assertTrue($result['byteOffset']);
    }

    public function testItToArrayByteOffsetIsFalseByDefault(): void
    {
        $sut = new UrlMultiCitation('https://example.com');
        $result = $sut->toArray();
        static::assertFalse($result['byteOffset']);
    }
}
