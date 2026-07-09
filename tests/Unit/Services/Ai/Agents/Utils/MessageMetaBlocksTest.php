<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Utils;

use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(MessageMetaBlocks::class)]
class MessageMetaBlocksTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new MessageMetaBlocks();
        static::assertInstanceOf(MessageMetaBlocks::class, $sut);
    }

    // =========================================================================
    // __toString — empty
    // =========================================================================

    public function testItReturnsEmptyStringWhenNoSectionsAdded(): void
    {
        $sut = new MessageMetaBlocks();
        static::assertSame('', (string)$sut);
    }

    // =========================================================================
    // addSection — string content
    // =========================================================================

    public function testItAddsSectionWithStringContent(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('my key', 'some content');
        $result = (string)$sut;

        static::assertStringContainsString('[HKI_META_MY_KEY]', $result);
        static::assertStringContainsString('some content', $result);
        static::assertStringContainsString('[/HKI_META_MY_KEY]', $result);
    }

    public function testItConvertsKeyToSnakeUpperCase(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('myLongKeyName', 'value');
        $result = (string)$sut;

        static::assertStringContainsString('[HKI_META_MY_LONG_KEY_NAME]', $result);
    }

    // =========================================================================
    // addSection — array content
    // =========================================================================

    public function testItJoinsArrayContentWithDoubleNewline(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('attachments', ['first paragraph', 'second paragraph']);
        $result = (string)$sut;

        static::assertStringContainsString("first paragraph\n\nsecond paragraph", $result);
    }

    // =========================================================================
    // addSection — overwrite
    // =========================================================================

    public function testItOverwritesPreviousSectionWithSameKey(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('mySection', 'original');
        $sut->addSection('mySection', 'replaced');
        $result = (string)$sut;

        static::assertStringNotContainsString('original', $result);
        static::assertStringContainsString('replaced', $result);
    }

    // =========================================================================
    // addSection — returns self
    // =========================================================================

    public function testItReturnsSelfForFluentChaining(): void
    {
        $sut = new MessageMetaBlocks();
        static::assertSame($sut, $sut->addSection('key', 'value'));
    }

    // =========================================================================
    // __toString — multiple sections
    // =========================================================================

    public function testItSeparatesMultipleSectionsWithDoubleNewline(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('first', 'a');
        $sut->addSection('second', 'b');
        $result = (string)$sut;

        static::assertStringContainsString("[/HKI_META_FIRST]\n\n[HKI_META_SECOND]", $result);
    }

    // =========================================================================
    // __toString — block format
    // =========================================================================

    public function testItWrapsContentBetweenOpenAndCloseTag(): void
    {
        $sut = new MessageMetaBlocks();
        $sut->addSection('test_section', 'the body');
        $result = (string)$sut;

        static::assertSame("[HKI_META_TEST_SECTION]\nthe body\n[/HKI_META_TEST_SECTION]", $result);
    }

    // =========================================================================
    // createBlock — static factory
    // =========================================================================

    public function testItCreatesBlockStatically(): void
    {
        $result = MessageMetaBlocks::createBlock('example', 'body text');
        static::assertSame("[HKI_META_EXAMPLE]\nbody text\n[/HKI_META_EXAMPLE]", $result);
    }

    public function testItCreatesBlockStaticallyWithArrayContent(): void
    {
        $result = MessageMetaBlocks::createBlock('example', ['part one', 'part two']);
        static::assertSame("[HKI_META_EXAMPLE]\npart one\n\npart two\n[/HKI_META_EXAMPLE]", $result);
    }

    // =========================================================================
    // wrapInstructions
    // =========================================================================

    public function testItWrapsInstructionsWithMetaExplanationPreamble(): void
    {
        $result = MessageMetaBlocks::wrapInstructions('Do the thing.');
        static::assertStringContainsString('HKI_META', $result);
        static::assertStringContainsString('Do the thing.', $result);
    }

    public function testItWrapsInstructionsWithSystemMetadataHeading(): void
    {
        $result = MessageMetaBlocks::wrapInstructions('Be helpful.');
        static::assertStringContainsString('# System metadata blocks (HKI_META)', $result);
    }

    public function testItWrapsInstructionsWithYourInstructionsHeading(): void
    {
        $result = MessageMetaBlocks::wrapInstructions('custom instructions here');
        static::assertStringContainsString('# Your instructions', $result);
        static::assertStringContainsString('custom instructions here', $result);
    }
}
