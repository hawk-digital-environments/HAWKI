<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Exceptions;

use App\Services\System\Database\Eloquent\ContextualScopes\Exceptions\InvalidScopeDefinitionException;
use App\Services\System\Database\Eloquent\ContextualScopes\Exceptions\ScopeExceptionInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidScopeDefinitionException::class)]
class InvalidScopeDefinitionExceptionTest extends TestCase
{
    public function testItIsInvalidArgumentException(): void
    {
        $sut = InvalidScopeDefinitionException::forMissingDefinition('my-scope', 'App\Models\Test');

        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsScopeExceptionInterface(): void
    {
        $sut = InvalidScopeDefinitionException::forMissingDefinition('my-scope', 'App\Models\Test');

        static::assertInstanceOf(ScopeExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forMissingDefinition
    // =========================================================================

    public function testItForMissingDefinitionMatchesExpectedMessage(): void
    {
        $sut = InvalidScopeDefinitionException::forMissingDefinition('active_filter', 'App\Models\AiModel');

        static::assertSame(
            "No scope definition found for scope key 'active_filter' in model 'App\Models\AiModel'.",
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidResolvedValue
    // =========================================================================

    public function testItForInvalidResolvedValueWithObjectIncludesClassName(): void
    {
        $sut = InvalidScopeDefinitionException::forInvalidResolvedValue('my-scope', 'App\Models\Test', new \stdClass());

        static::assertStringContainsString(\stdClass::class, $sut->getMessage());
    }

    public function testItForInvalidResolvedValueWithScalarIncludesType(): void
    {
        $sut = InvalidScopeDefinitionException::forInvalidResolvedValue('my-scope', 'App\Models\Test', 42);

        static::assertStringContainsString('integer', $sut->getMessage());
    }

    public function testItForInvalidResolvedValueMatchesExpectedMessage(): void
    {
        $sut = InvalidScopeDefinitionException::forInvalidResolvedValue('active_filter', 'App\Models\AiModel', new \stdClass());

        static::assertSame(
            "Scope definition for scope key 'active_filter' in model 'App\Models\AiModel' must resolve to an instance of Scope. Got: stdClass",
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidDefinitionType
    // =========================================================================

    public function testItForInvalidDefinitionTypeMatchesExpectedMessage(): void
    {
        $sut = InvalidScopeDefinitionException::forInvalidDefinitionType('active_filter', 'App\Models\AiModel');

        static::assertSame(
            "Invalid scope definition for scope key 'active_filter' in model 'App\Models\AiModel'. Expected instance of Scope, Closure, or class name string.",
            $sut->getMessage()
        );
    }
}
