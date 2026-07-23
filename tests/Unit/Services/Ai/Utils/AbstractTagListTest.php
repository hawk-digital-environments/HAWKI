<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Utils;

use App\Services\Ai\Utils\AbstractTagList;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Utils\AbstractTagListTestFixtures\ConcreteTagList;

#[CoversClass(AbstractTagList::class)]
class AbstractTagListTest extends TestCase
{
    // =========================================================================
    // Construction / fromArray
    // =========================================================================

    public function testItConstructsViaFromArray(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image']);
        static::assertInstanceOf(ConcreteTagList::class, $sut);
    }

    public function testItDeduplicatesValuesOnConstruction(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'text', 'image']);
        static::assertSame(['text', 'image'], $sut->toArray());
    }

    public function testItNormalisesToLowercaseOnConstruction(): void
    {
        $sut = ConcreteTagList::fromArray(['TEXT', 'Image']);
        static::assertSame(['text', 'image'], $sut->toArray());
    }

    public function testItTrimmsWhitespaceOnConstruction(): void
    {
        $sut = ConcreteTagList::fromArray([' text ', '  image  ']);
        static::assertSame(['text', 'image'], $sut->toArray());
    }

    public function testItTreatsDuplicatesDifferingOnlyInCaseAsOne(): void
    {
        $sut = ConcreteTagList::fromArray(['TEXT', 'text', 'Text']);
        static::assertSame(['text'], $sut->toArray());
    }

    public function testItAcceptsEmptyArray(): void
    {
        $sut = ConcreteTagList::fromArray([]);
        static::assertSame([], $sut->toArray());
    }

    // =========================================================================
    // has
    // =========================================================================

    public function testItHasReturnsTrueForExistingTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertTrue($sut->has('text'));
    }

    public function testItHasReturnsFalseForMissingTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertFalse($sut->has('image'));
    }

    public function testItHasIsCaseInsensitive(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertTrue($sut->has('TEXT'));
    }

    public function testItHasTrimsInputBeforeChecking(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertTrue($sut->has('  text  '));
    }

    // =========================================================================
    // add
    // =========================================================================

    public function testItAddAppendsNewTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        $sut->add('image');
        static::assertSame(['text', 'image'], $sut->toArray());
    }

    public function testItAddDoesNotDuplicateExistingTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        $sut->add('TEXT');
        static::assertSame(['text'], $sut->toArray());
    }

    public function testItAddReturnsSelf(): void
    {
        $sut = ConcreteTagList::fromArray([]);
        static::assertSame($sut, $sut->add('text'));
    }

    // =========================================================================
    // remove
    // =========================================================================

    public function testItRemoveDeletesExistingTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image']);
        $sut->remove('text');
        static::assertSame(['image'], $sut->toArray());
    }

    public function testItRemoveDoesNothingForAbsentTag(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        $sut->remove('audio');
        static::assertSame(['text'], $sut->toArray());
    }

    public function testItRemoveIsCaseInsensitive(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image']);
        $sut->remove('TEXT');
        static::assertSame(['image'], $sut->toArray());
    }

    public function testItRemoveReturnsSelf(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertSame($sut, $sut->remove('text'));
    }

    public function testItRemoveReindexesArrayAfterRemoval(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image', 'audio']);
        $sut->remove('image');
        // toArray must return a plain 0-indexed array, not a sparse one
        static::assertSame(['text', 'audio'], array_values($sut->toArray()));
    }

    // =========================================================================
    // jsonSerialize / getIterator
    // =========================================================================

    public function testItJsonSerializesAsArray(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image']);
        static::assertSame(['text', 'image'], $sut->jsonSerialize());
    }

    public function testItIsJsonEncodable(): void
    {
        $sut = ConcreteTagList::fromArray(['text']);
        static::assertSame('["text"]', json_encode($sut));
    }

    public function testItIsIterable(): void
    {
        $sut = ConcreteTagList::fromArray(['text', 'image']);
        $collected = [];
        foreach ($sut as $value) {
            $collected[] = $value;
        }
        static::assertSame(['text', 'image'], $collected);
    }
}
