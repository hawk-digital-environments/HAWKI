<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Utils\Traits;

use App\Services\Ai\Utils\Traits\TranslatableRegistryTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Services\Ai\Utils\Traits\TranslatableRegistryTraitTestFixtures\ConcreteRegistry;

#[CoversTrait(TranslatableRegistryTrait::class)]
class TranslatableRegistryTraitTest extends TestCase
{
    private ConcreteRegistry $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new ConcreteRegistry();
    }

    // =========================================================================
    // getTitleLabel
    // =========================================================================

    public function testItGetTitleLabelReturnsNullWhenNoneWasDeclared(): void
    {
        $this->sut->declare('my_key', null);
        static::assertNull($this->sut->getTitleLabel('my_key'));
    }

    public function testItGetTitleLabelReturnsTheDeclaredLabel(): void
    {
        $this->sut->declare('my_key', 'translation.title');
        static::assertSame('translation.title', $this->sut->getTitleLabel('my_key'));
    }

    public function testItGetTitleLabelThrowsForUndeclaredKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The key 'unknown' is not declared in");
        $this->sut->getTitleLabel('unknown');
    }

    // =========================================================================
    // getDescriptionLabel
    // =========================================================================

    public function testItGetDescriptionLabelReturnsNullWhenNoneWasDeclared(): void
    {
        $this->sut->declare('my_key', null, null);
        static::assertNull($this->sut->getDescriptionLabel('my_key'));
    }

    public function testItGetDescriptionLabelReturnsTheDeclaredLabel(): void
    {
        $this->sut->declare('my_key', null, 'translation.description');
        static::assertSame('translation.description', $this->sut->getDescriptionLabel('my_key'));
    }

    public function testItGetDescriptionLabelThrowsForUndeclaredKey(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("The key 'unknown' is not declared in");
        $this->sut->getDescriptionLabel('unknown');
    }

    // =========================================================================
    // Independence of title and description labels
    // =========================================================================

    public function testItStorestTitleAndDescriptionIndependently(): void
    {
        $this->sut->declare('key_a', 'title.a', 'desc.a');
        $this->sut->declare('key_b', 'title.b', 'desc.b');

        static::assertSame('title.a', $this->sut->getTitleLabel('key_a'));
        static::assertSame('desc.a', $this->sut->getDescriptionLabel('key_a'));
        static::assertSame('title.b', $this->sut->getTitleLabel('key_b'));
        static::assertSame('desc.b', $this->sut->getDescriptionLabel('key_b'));
    }
}
