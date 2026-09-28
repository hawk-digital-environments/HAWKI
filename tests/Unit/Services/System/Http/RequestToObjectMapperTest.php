<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Http;

use App\Services\System\Http\RequestToObjectMapper;
use App\Services\Users\Settings\Values\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures\Direction;
use Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures\IntStatus;
use Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures\MappableSettings;

#[CoversClass(RequestToObjectMapper::class)]
class RequestToObjectMapperTest extends TestCase
{
    private RequestToObjectMapper $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new RequestToObjectMapper();
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(RequestToObjectMapper::class, $this->sut);
    }

    // =========================================================================
    // map — fresh instance
    // =========================================================================

    public function testItMapStartsFromTheClassDefaults(): void
    {
        $result = $this->sut->map(MappableSettings::class, []);

        self::assertSame(Theme::Light, $result->theme);
        self::assertSame(4096, $result->max_tokens);
        self::assertNull($result->locale);
    }

    public function testItMapFillsAnnotatedProperties(): void
    {
        $result = $this->sut->map(MappableSettings::class, [
            'theme' => 'dark',
            'locale' => 'de_DE',
        ]);

        self::assertSame(Theme::Dark, $result->theme);
        self::assertSame('de_DE', $result->locale);
        // Untouched properties keep their defaults.
        self::assertSame(4096, $result->max_tokens);
    }

    // =========================================================================
    // mapOnto — partial updates
    // =========================================================================

    public function testItMapOntoMergesIntoTheCurrentInstance(): void
    {
        $current = MappableSettings::fromStringArray([
            'theme' => 'dark',
            'locale' => 'de_DE',
        ]);

        $result = $this->sut->mapOnto($current, ['theme' => 'light']);

        // The touched property is overwritten, the untouched one keeps its value —
        // JSON:API PATCH semantics: missing attributes keep the current value.
        self::assertSame(Theme::Light, $result->theme);
        self::assertSame('de_DE', $result->locale);
    }

    public function testItMapOntoReturnsANewInstanceAndKeepsTheOriginal(): void
    {
        $current = MappableSettings::fromStringArray(['theme' => 'light']);

        $result = $this->sut->mapOnto($current, ['theme' => 'dark']);

        self::assertNotSame($current, $result);
        self::assertSame(Theme::Light, $current->theme);
        self::assertSame(Theme::Dark, $result->theme);
    }

    public function testItMapOntoPreservesEncryptedAndCustomValuesThroughTheRoundTrip(): void
    {
        // mapOnto rehydrates the *current* values through the class's casts — values a
        // save would persist survive the same way.
        $current = MappableSettings::fromStringArray(['max_tokens' => '8192', 'stream' => '0']);

        $result = $this->sut->mapOnto($current, ['theme' => 'dark']);

        self::assertSame(8192, $result->max_tokens);
        self::assertFalse($result->stream);
        self::assertSame(Theme::Dark, $result->theme);
    }

    public function testItMapOntoNeverFillsUnAnnotatedProperties(): void
    {
        $current = MappableSettings::fromStringArray([]);

        $result = $this->sut->mapOnto($current, ['internal' => 'hacked']);

        self::assertSame('keep-me', $result->internal);
    }

    // =========================================================================
    // cast juggling — typed wire values hydrate through the property's cast
    // =========================================================================

    #[DataProvider('provideTestItHydratesValidatedWireValuesData')]
    public function testItHydratesValidatedWireValues(array $validatedData, mixed $expected, string $property): void
    {
        $result = $this->sut->map(MappableSettings::class, $validatedData);

        self::assertSame($expected, $result->{$property});
    }

    public static function provideTestItHydratesValidatedWireValuesData(): iterable
    {
        yield 'enum name as wire string' => [['theme' => 'dark'], Theme::Dark, 'theme'];

        yield 'enum instance (already typed)' => [['theme' => Theme::Dark], Theme::Dark, 'theme'];

        yield 'int wire value' => [['max_tokens' => 8192], 8192, 'max_tokens'];

        yield 'int wire value as string' => [['max_tokens' => '8192'], 8192, 'max_tokens'];

        yield 'bool wire value' => [['stream' => false], false, 'stream'];

        yield 'bool wire value as string' => [['stream' => '0'], false, 'stream'];

        yield 'null resets to default' => [['locale' => null], null, 'locale'];

        yield 'int backed enum from wire string' => [['status' => '2'], IntStatus::Inactive, 'status'];

        yield 'int backed enum from instance' => [['status' => IntStatus::Inactive], IntStatus::Inactive, 'status'];

        yield 'unit enum by case name' => [['direction' => 'South'], Direction::South, 'direction'];
    }
}
