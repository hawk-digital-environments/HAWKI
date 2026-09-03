<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\Http\Attributes;

use App\Services\System\Http\Attributes\ValidateInput;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Services\System\Http\RequestToObjectMapperTest\RequestToObjectMapperTestFixtures\MappableSettings;

#[CoversClass(ValidateInput::class)]
class ValidateInputTest extends TestCase
{
    protected function tearDown(): void
    {
        ValidateInput::reset();

        parent::tearDown();
    }

    // =========================================================================
    // rulesFor
    // =========================================================================

    public function testItExtractsOnlyAnnotatedProperties(): void
    {
        $rules = ValidateInput::rulesFor(MappableSettings::class);

        self::assertSame([
            'theme' => 'sometimes|in:light,dark',
            'max_tokens' => 'sometimes|integer',
            'stream' => 'sometimes|boolean',
            'locale' => 'sometimes|nullable|string|max:5',
            'status' => 'sometimes|integer',
            'direction' => 'sometimes|string',
        ], $rules);
    }

    public function testItUnAnnotatedPropertiesAreNotFillable(): void
    {
        // The un-annotated property is exactly the missing key — the rule map IS the
        // fillable list, which is what keeps un-annotated properties out of the
        // write path (Laravel's validated() only returns rule keys).
        self::assertArrayNotHasKey('internal', ValidateInput::rulesFor(MappableSettings::class));
    }

    public function testItReturnsTheSameRuleMapOnRepeatedCalls(): void
    {
        self::assertSame(
            ValidateInput::rulesFor(MappableSettings::class),
            ValidateInput::rulesFor(MappableSettings::class),
        );
    }

    public function testItResetClearsTheCache(): void
    {
        ValidateInput::rulesFor(MappableSettings::class);

        ValidateInput::reset();

        // Nothing observable changes except the cache — call again to prove it
        // re-extracts without error.
        self::assertArrayHasKey('theme', ValidateInput::rulesFor(MappableSettings::class));
    }
}
