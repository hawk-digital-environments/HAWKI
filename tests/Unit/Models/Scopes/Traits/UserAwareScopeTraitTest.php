<?php

declare(strict_types=1);

namespace Tests\Unit\Models\Scopes\Traits;

use App\Models\Scopes\Traits\UserAwareScopeTrait;
use App\Models\User;
use App\Services\System\Container\ServiceLocator;
use App\Services\System\Container\SystemEnvironment;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;
use Tests\Unit\Models\Scopes\Traits\UserAwareScopeTraitTestFixtures\UserAwareScopeHost;

#[CoversClass(UserAwareScopeTrait::class)]
class UserAwareScopeTraitTest extends TestCase
{
    use RefreshDatabase;

    private UserAwareScopeHost $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the request->user() resolver is bound by the framework during the HTTP request lifecycle
        // (see AuthServiceProvider::registerRequestRebindHandler), which does not run in a unit test.
        // Mirror that wiring here so $request->user() resolves through the auth manager.
        $this->app['request']->setUserResolver(fn ($guard = null) => $this->app['auth']->guard($guard)->user());

        $this->sut = new UserAwareScopeHost();
        $this->sut->initializeServiceLocatingScopeTrait($this->app->make(ServiceLocator::class));
        $this->sut->initializeUserAwareScopeTrait($this->app->make(SystemEnvironment::class));
    }

    public function testItResolvesUserFromRequestEvenWhenDefaultGuardSingletonIsStale(): void
    {
        $user = User::factory()->create();

        // Reproduce the production ordering: the Illuminate\Contracts\Auth\Guard binding
        // (the auth.driver singleton) materializes as the default 'web' guard BEFORE the
        // default driver is switched to 'sanctum'. The web guard never holds the
        // token-authenticated user, so resolving via Guard would return null here.
        $this->app->make(Guard::class);

        // SystemContextBootingMiddleware then authenticates via Sanctum and switches the default.
        $this->actingAs($user, 'sanctum');

        self::assertSame($user, $this->sut->exposeCurrentUser());
    }

    public function testItReturnsNullWhenNoUserIsAuthenticated(): void
    {
        self::assertNull($this->sut->exposeCurrentUser());
    }

    public function testItResolvesUserInConsoleWhereRequestHasNoUserResolver(): void
    {
        // Console reality: unlike the HTTP lifecycle, the request's user-resolver is NOT
        // wired to the auth manager. A command sets the user directly on the guard via
        // auth()->setUser() (see CreateSanctumTokenForUser:82). Undo setUp()'s HTTP-style
        // wiring so we reproduce a real console request.
        $this->app['request']->setUserResolver(static fn ($guard = null) => null);

        $user = User::factory()->create();
        $this->app['auth']->setUser($user);

        self::assertSame($user, $this->sut->exposeCurrentUser());
    }
}
