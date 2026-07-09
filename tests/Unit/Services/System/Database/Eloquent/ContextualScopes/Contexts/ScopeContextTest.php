<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes\Contexts;

use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ModelScopeContext;
use App\Services\System\Database\Eloquent\ContextualScopes\Contexts\ScopeContext;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ScopeContext::class)]
class ScopeContextTest extends TestCase
{
    private function makeSut(\Closure|null $guard = null): ScopeContext
    {
        return new ScopeContext($guard ?? fn() => true);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();

        static::assertInstanceOf(ScopeContext::class, $sut);
    }

    public function testItConstructsWithProvidedGuard(): void
    {
        $guard = fn() => true;
        $sut = new ScopeContext($guard);

        static::assertSame($guard, $sut->getDefaultIsDisablingAllowedGuard());
    }

    // =========================================================================
    // setDefaultIsDisablingAllowedGuard / getDefaultIsDisablingAllowedGuard
    // =========================================================================

    public function testItSetDefaultIsDisablingAllowedGuardReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setDefaultIsDisablingAllowedGuard(fn() => false));
    }

    public function testItSetDefaultIsDisablingAllowedGuardReplacesGuard(): void
    {
        $newGuard = fn() => false;
        $sut = $this->makeSut();
        $sut->setDefaultIsDisablingAllowedGuard($newGuard);

        static::assertSame($newGuard, $sut->getDefaultIsDisablingAllowedGuard());
    }

    // =========================================================================
    // setDefaultOnDisabledNotAllowed
    // =========================================================================

    public function testItSetDefaultOnDisabledNotAllowedReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setDefaultOnDisabledNotAllowed(fn() => null));
    }

    // =========================================================================
    // setAllScopesGloballyDisabled
    // =========================================================================

    public function testItSetAllScopesGloballyDisabledReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setAllScopesGloballyDisabled(true));
    }

    public function testItSetAllScopesGloballyDisabledDisablesAllScopes(): void
    {
        $sut = $this->makeSut();
        $sut->setAllScopesGloballyDisabled(true);

        $modelCtx = $sut->getModelContext('App\\Models\\Test');

        static::assertTrue($modelCtx->isScopeDisabled('any-scope'));
    }

    public function testItSetAllScopesGloballyDisabledCanBeReset(): void
    {
        $sut = $this->makeSut();
        $sut->setAllScopesGloballyDisabled(true);
        $sut->setAllScopesGloballyDisabled(false);

        $modelCtx = $sut->getModelContext('App\\Models\\Test');

        static::assertFalse($modelCtx->isScopeDisabled('any-scope'));
    }

    // =========================================================================
    // getModelContext
    // =========================================================================

    public function testItGetModelContextReturnsModelScopeContext(): void
    {
        $sut = $this->makeSut();

        $ctx = $sut->getModelContext('App\\Models\\Test');

        static::assertInstanceOf(ModelScopeContext::class, $ctx);
    }

    public function testItGetModelContextReturnsSameInstanceForSameClass(): void
    {
        $sut = $this->makeSut();

        $first = $sut->getModelContext('App\\Models\\Test');
        $second = $sut->getModelContext('App\\Models\\Test');

        static::assertSame($first, $second);
    }

    public function testItGetModelContextReturnsDifferentInstancesForDifferentClasses(): void
    {
        $sut = $this->makeSut();

        $first = $sut->getModelContext('App\\Models\\ModelA');
        $second = $sut->getModelContext('App\\Models\\ModelB');

        static::assertNotSame($first, $second);
    }

    public function testItGetModelContextSetsCorrectModelClass(): void
    {
        $sut = $this->makeSut();

        $ctx = $sut->getModelContext('App\\Models\\MyModel');

        static::assertSame('App\\Models\\MyModel', $ctx->modelClass);
    }

    // =========================================================================
    // runSandboxed
    // =========================================================================

    public function testItRunSandboxedRestoresStateAfterCallback(): void
    {
        $sut = $this->makeSut();
        $sut->setAllScopesGloballyDisabled(false);

        $sut->runSandboxed(function (ScopeContext $ctx) {
            $ctx->setAllScopesGloballyDisabled(true);
        });

        $modelCtx = $sut->getModelContext('App\\Models\\Test');
        static::assertFalse($modelCtx->isScopeDisabled('any-scope'));
    }

    public function testItRunSandboxedRestoresStateOnException(): void
    {
        $sut = $this->makeSut();

        try {
            $sut->runSandboxed(function (ScopeContext $ctx) {
                $ctx->setAllScopesGloballyDisabled(true);
                throw new \RuntimeException('test');
            });
        } catch (\RuntimeException) {
        }

        $modelCtx = $sut->getModelContext('App\\Models\\Test');
        static::assertFalse($modelCtx->isScopeDisabled('any-scope'));
    }

    public function testItRunSandboxedReturnsCallbackReturnValue(): void
    {
        $sut = $this->makeSut();

        $result = $sut->runSandboxed(fn(ScopeContext $ctx) => 'value');

        static::assertSame('value', $result);
    }

    public function testItRunSandboxedPassesScopeContextToCallback(): void
    {
        $sut = $this->makeSut();
        $received = null;

        $sut->runSandboxed(function (ScopeContext $ctx) use (&$received) {
            $received = $ctx;
        });

        static::assertSame($sut, $received);
    }

    public function testItRunSandboxedRestoresModelScopeStateAfterCallback(): void
    {
        $sut = $this->makeSut();
        $modelCtx = $sut->getModelContext('App\\Models\\Test');

        $sut->runSandboxed(function (ScopeContext $ctx) {
            $ctx->getModelContext('App\\Models\\Test')->disableScope('my-scope');
        });

        static::assertFalse($modelCtx->isScopeDisabled('my-scope'));
    }
}
