<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\Database\Eloquent\ContextualScopes;

use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ScopeRegistrar::class)]
class ScopeRegistrarTest extends TestCase
{
    private \Closure $defaultGuard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->defaultGuard = fn() => true;
    }

    private function makeSut(string $modelClass = 'App\\Models\\Test'): ScopeRegistrar
    {
        return new ScopeRegistrar($modelClass, $this->defaultGuard);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();

        static::assertInstanceOf(ScopeRegistrar::class, $sut);
        static::assertSame('App\\Models\\Test', $sut->modelClass);
    }

    // =========================================================================
    // addScope / hasScope / getScopeDefinition
    // =========================================================================

    public function testItAddScopeStoresDefinition(): void
    {
        $scope = static fn() => null;
        $sut = $this->makeSut();
        $sut->addScope('my-scope', $scope);

        static::assertTrue($sut->hasScope('my-scope'));
        static::assertSame($scope, $sut->getScopeDefinition('my-scope'));
    }

    public function testItAddScopeReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->addScope('my-scope', static fn() => null));
    }

    public function testItHasScopeReturnsFalseWhenNotRegistered(): void
    {
        $sut = $this->makeSut();

        static::assertFalse($sut->hasScope('unknown-scope'));
    }

    public function testItGetScopeDefinitionReturnsNullForUnknownScope(): void
    {
        $sut = $this->makeSut();

        static::assertNull($sut->getScopeDefinition('unknown-scope'));
    }

    public function testItAddScopeAcceptsScopeInstance(): void
    {
        $scope = $this->createMock(Scope::class);
        $sut = $this->makeSut();
        $sut->addScope('my-scope', $scope);

        static::assertSame($scope, $sut->getScopeDefinition('my-scope'));
    }

    public function testItAddScopeAcceptsClassName(): void
    {
        $sut = $this->makeSut();
        $sut->addScope('my-scope', 'App\\Services\\MyScope');

        static::assertSame('App\\Services\\MyScope', $sut->getScopeDefinition('my-scope'));
    }

    // =========================================================================
    // removeScope
    // =========================================================================

    public function testItRemoveScopeRemovesRegisteredScope(): void
    {
        $sut = $this->makeSut();
        $sut->addScope('my-scope', static fn() => null);
        $sut->removeScope('my-scope');

        static::assertFalse($sut->hasScope('my-scope'));
    }

    public function testItRemoveScopeReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->removeScope('nonexistent'));
    }

    // =========================================================================
    // getDisablingGuard
    // =========================================================================

    public function testItGetDisablingGuardReturnsDefaultWhenNoSpecificGuardRegistered(): void
    {
        $sut = $this->makeSut();
        $sut->addScope('my-scope', static fn() => null);

        static::assertSame($this->defaultGuard, $sut->getDisablingGuard('my-scope'));
    }

    public function testItGetDisablingGuardReturnsScopeSpecificGuard(): void
    {
        $specificGuard = fn() => false;
        $sut = $this->makeSut();
        $sut->addScope('my-scope', static fn() => null, $specificGuard);

        static::assertSame($specificGuard, $sut->getDisablingGuard('my-scope'));
    }

    public function testItRemoveScopeAlsoRemovesItsGuard(): void
    {
        $specificGuard = fn() => false;
        $sut = $this->makeSut();
        $sut->addScope('my-scope', static fn() => null, $specificGuard);
        $sut->removeScope('my-scope');

        // After re-adding without a guard, the default should be returned again.
        $sut->addScope('my-scope', static fn() => null);

        static::assertSame($this->defaultGuard, $sut->getDisablingGuard('my-scope'));
    }

    // =========================================================================
    // setDefaultDisablingGuard / resetDefaultDisablingGuard
    // =========================================================================

    public function testItSetDefaultDisablingGuardChangesDefaultForSubsequentScopes(): void
    {
        $newGuard = fn() => false;
        $sut = $this->makeSut();
        $sut->setDefaultDisablingGuard($newGuard);
        $sut->addScope('my-scope', static fn() => null);

        static::assertSame($newGuard, $sut->getDisablingGuard('my-scope'));
    }

    public function testItSetDefaultDisablingGuardReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->setDefaultDisablingGuard(fn() => true));
    }

    public function testItResetDefaultDisablingGuardRestoresOriginal(): void
    {
        $sut = $this->makeSut();
        $sut->setDefaultDisablingGuard(fn() => false);
        $sut->resetDefaultDisablingGuard();
        $sut->addScope('my-scope', static fn() => null);

        static::assertSame($this->defaultGuard, $sut->getDisablingGuard('my-scope'));
    }

    public function testItResetDefaultDisablingGuardReturnsSelf(): void
    {
        $sut = $this->makeSut();

        static::assertSame($sut, $sut->resetDefaultDisablingGuard());
    }

    // =========================================================================
    // Iteration
    // =========================================================================

    public function testItIteratesOverRegisteredScopeKeys(): void
    {
        $sut = $this->makeSut();
        $sut->addScope('scope-a', static fn() => null);
        $sut->addScope('scope-b', static fn() => null);

        $keys = [];
        foreach ($sut as $key) {
            $keys[] = $key;
        }

        static::assertSame(['scope-a', 'scope-b'], $keys);
    }

    public function testItIteratesEmptyWhenNoScopesRegistered(): void
    {
        $sut = $this->makeSut();
        $keys = iterator_to_array($sut);

        static::assertSame([], $keys);
    }

    public function testItIteratesAfterRemoveReflectsRemoval(): void
    {
        $sut = $this->makeSut();
        $sut->addScope('scope-a', static fn() => null);
        $sut->addScope('scope-b', static fn() => null);
        $sut->removeScope('scope-a');

        $keys = iterator_to_array($sut);

        static::assertSame(['scope-b'], $keys);
    }
}
