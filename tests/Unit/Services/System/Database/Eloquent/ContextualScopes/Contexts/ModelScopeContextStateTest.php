<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContextState;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelScopeContextState::class)]
class ModelScopeContextStateTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructsWithDefaults(): void
    {
        $sut = new ModelScopeContextState();

        static::assertInstanceOf(ModelScopeContextState::class, $sut);
        static::assertNull($sut->defaultOnDisableNotAllowed);
        static::assertFalse($sut->allScopesLocallyDisabled);
        static::assertNull($sut->allScopesLocallyDisabledNotAllowed);
        static::assertSame([], $sut->disabledScopes);
    }

    public function testItConstructsWithExplicitValues(): void
    {
        $callback = static fn() => null;

        $sut = new ModelScopeContextState(
            defaultOnDisableNotAllowed: $callback,
            allScopesLocallyDisabled: true,
            allScopesLocallyDisabledNotAllowed: $callback,
            disabledScopes: ['scope-a' => null],
        );

        static::assertSame($callback, $sut->defaultOnDisableNotAllowed);
        static::assertTrue($sut->allScopesLocallyDisabled);
        static::assertSame($callback, $sut->allScopesLocallyDisabledNotAllowed);
        static::assertArrayHasKey('scope-a', $sut->disabledScopes);
    }

    // =========================================================================
    // clone
    // =========================================================================

    public function testItCloneReturnsNewInstance(): void
    {
        $sut = new ModelScopeContextState();

        $clone = $sut->clone();

        static::assertNotSame($sut, $clone);
        static::assertInstanceOf(ModelScopeContextState::class, $clone);
    }

    public function testItClonePreservesDefaultOnDisableNotAllowed(): void
    {
        $callback = static fn() => false;
        $sut = new ModelScopeContextState(defaultOnDisableNotAllowed: $callback);

        $clone = $sut->clone();

        static::assertSame($callback, $clone->defaultOnDisableNotAllowed);
    }

    public function testItClonePreservesAllScopesLocallyDisabled(): void
    {
        $sut = new ModelScopeContextState(allScopesLocallyDisabled: true);

        $clone = $sut->clone();

        static::assertTrue($clone->allScopesLocallyDisabled);
    }

    public function testItClonePreservesDisabledScopes(): void
    {
        $sut = new ModelScopeContextState(disabledScopes: ['my-scope' => null]);

        $clone = $sut->clone();

        static::assertArrayHasKey('my-scope', $clone->disabledScopes);
    }

    public function testItCloneMutationsDoNotAffectOriginal(): void
    {
        $sut = new ModelScopeContextState();
        $clone = $sut->clone();

        $clone->allScopesLocallyDisabled = true;
        $clone->disabledScopes['new-scope'] = null;

        static::assertFalse($sut->allScopesLocallyDisabled);
        static::assertArrayNotHasKey('new-scope', $sut->disabledScopes);
    }
}
