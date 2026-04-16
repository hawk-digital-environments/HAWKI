<?php
declare(strict_types=1);

namespace Tests\Unit\Utils;

use App\Utils\JsonSerializableTrait;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;
use Tests\Unit\Utils\JsonSerializableTraitTestFixtures\NoPublicPropertiesModel;
use Tests\Unit\Utils\JsonSerializableTraitTestFixtures\SampleJsonModel;

#[CoversTrait(JsonSerializableTrait::class)]
class JsonSerializableTraitTest extends TestCase
{
    // =========================================================================
    // Included properties
    // =========================================================================

    public function testItIncludesPublicProperties(): void
    {
        $sut = new SampleJsonModel();

        $result = $sut->jsonSerialize();

        static::assertArrayHasKey('name', $result);
        static::assertSame('alice', $result['name']);
        static::assertArrayHasKey('age', $result);
        static::assertSame(30, $result['age']);
    }

    public function testItReturnsNullForUninitializedPublicProperty(): void
    {
        $sut = new SampleJsonModel();

        $result = $sut->jsonSerialize();

        static::assertArrayHasKey('uninitializedProp', $result);
        static::assertNull($result['uninitializedProp']);
    }

    // =========================================================================
    // Excluded properties
    // =========================================================================

    public function testItExcludesProtectedProperties(): void
    {
        $sut = new SampleJsonModel();

        $result = $sut->jsonSerialize();

        static::assertArrayNotHasKey('hidden', $result);
    }

    public function testItExcludesPrivateProperties(): void
    {
        $sut = new SampleJsonModel();

        $result = $sut->jsonSerialize();

        static::assertArrayNotHasKey('internal', $result);
    }

    public function testItExcludesStaticProperties(): void
    {
        $sut = new SampleJsonModel();

        $result = $sut->jsonSerialize();

        static::assertArrayNotHasKey('staticProp', $result);
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function testItReturnsEmptyArrayWhenNoPublicProperties(): void
    {
        $sut = new NoPublicPropertiesModel();

        $result = $sut->jsonSerialize();

        static::assertSame([], $result);
    }

    public function testItProducesValidJsonWhenPassedToJsonEncode(): void
    {
        $sut = new SampleJsonModel();

        $json = json_encode($sut);

        static::assertIsString($json);
        $decoded = json_decode($json, true);
        static::assertSame('alice', $decoded['name']);
        static::assertSame(30, $decoded['age']);
    }
}
