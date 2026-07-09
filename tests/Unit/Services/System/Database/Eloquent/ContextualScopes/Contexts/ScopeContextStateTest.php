<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContextState;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContextState;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ScopeContextState::class)]
class ScopeContextStateTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $guard = fn() => true;
        $onNotAllowed = fn() => null;

        $sut = new ScopeContextState($guard, $onNotAllowed);

        static::assertInstanceOf(ScopeContextState::class, $sut);
        static::assertSame($guard, $sut->defaultIsDisablingAllowedGuard);
        static::assertSame($onNotAllowed, $sut->defaultOnDisableNotAllowed);
        static::assertFalse($sut->allScopesGloballyDisabled);
        static::assertNull($sut->onAllScopesGloballyDisabledNotAllowed);
    }

    // =========================================================================
    // getOrMakeModelScopeContextState
    // =========================================================================

    public function testItGetOrMakeModelScopeContextStateCreatesNewState(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);

        $state = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');

        static::assertInstanceOf(ModelScopeContextState::class, $state);
    }

    public function testItGetOrMakeModelScopeContextStateReturnsSameInstanceOnSubsequentCalls(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);

        $first = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');
        $second = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');

        static::assertSame($first, $second);
    }

    public function testItGetOrMakeModelScopeContextStateReturnsDifferentInstancesForDifferentModels(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);

        $first = $sut->getOrMakeModelScopeContextState('App\\Models\\ModelA');
        $second = $sut->getOrMakeModelScopeContextState('App\\Models\\ModelB');

        static::assertNotSame($first, $second);
    }

    // =========================================================================
    // clone
    // =========================================================================

    public function testItCloneReturnsNewInstance(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);

        $clone = $sut->clone();

        static::assertNotSame($sut, $clone);
        static::assertInstanceOf(ScopeContextState::class, $clone);
    }

    public function testItClonePreservesAllScopesGloballyDisabled(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null, allScopesGloballyDisabled: true);

        $clone = $sut->clone();

        static::assertTrue($clone->allScopesGloballyDisabled);
    }

    public function testItCloneDeepCopiesModelStates(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);
        $modelState = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');

        $clone = $sut->clone();
        $clonedModelState = $clone->getOrMakeModelScopeContextState('App\\Models\\Test');

        // Mutations to the clone's model state must not affect the original
        $clonedModelState->allScopesLocallyDisabled = true;

        static::assertFalse($modelState->allScopesLocallyDisabled);
    }

    // =========================================================================
    // restore
    // =========================================================================

    public function testItRestoreRestoresGloballyDisabledFlag(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);
        $backup = $sut->clone();

        $sut->allScopesGloballyDisabled = true;
        $sut->restore($backup);

        static::assertFalse($sut->allScopesGloballyDisabled);
    }

    public function testItRestoreRestoresDefaultOnDisableNotAllowed(): void
    {
        $original = fn() => null;
        $replacement = fn() => true;

        $sut = new ScopeContextState(fn() => true, $original);
        $backup = $sut->clone();

        $sut->defaultOnDisableNotAllowed = $replacement;
        $sut->restore($backup);

        static::assertSame($original, $sut->defaultOnDisableNotAllowed);
    }

    public function testItRestoreRestoresModelStates(): void
    {
        $sut = new ScopeContextState(fn() => true, fn() => null);
        $modelState = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');
        $backup = $sut->clone();

        $modelState->allScopesLocallyDisabled = true;
        $sut->restore($backup);

        $restoredModelState = $sut->getOrMakeModelScopeContextState('App\\Models\\Test');
        static::assertFalse($restoredModelState->allScopesLocallyDisabled);
    }
}
