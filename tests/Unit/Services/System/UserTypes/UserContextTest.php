<?php

declare(strict_types=1);

namespace Tests\Unit\Services\System\UserTypes;

use App\Models\User;
use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\Events\UserTypeChangedEvent;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Contracts\Auth\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserContext::class)]
class UserContextTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Constructor
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->createSut();

        self::assertInstanceOf(UserContext::class, $sut);
    }

    // =========================================================================
    // Defaults
    // =========================================================================

    public function testItIsGuestByDefault(): void
    {
        $sut = $this->createSut();

        self::assertTrue($sut->isGuest());
    }

    public function testItIsNotRegisteringUserByDefault(): void
    {
        $sut = $this->createSut();

        self::assertFalse($sut->isRegisteringUser());
    }

    public function testItIsNotUserByDefault(): void
    {
        $sut = $this->createSut();

        self::assertFalse($sut->isUser());
    }

    public function testItIsNotExternalAppByDefault(): void
    {
        $sut = $this->createSut();

        self::assertFalse($sut->isExternalApp());
    }

    public function testItGetReturnsGuestByDefault(): void
    {
        $sut = $this->createSut();

        self::assertSame(WellKnownUserTypes::GUEST, $sut->get());
    }

    public function testItIsReturnsTrueForGuestByDefault(): void
    {
        $sut = $this->createSut();

        self::assertTrue($sut->is(WellKnownUserTypes::GUEST));
    }

    public function testItIsReturnsFalseForNonDefaultType(): void
    {
        $sut = $this->createSut();

        self::assertFalse($sut->is(WellKnownUserTypes::USER));
    }

    public function testItHasNoRegisteringUserByDefault(): void
    {
        $sut = $this->createSut();

        self::assertNull($sut->getRegisteringUser());
    }

    public function testItHasNoAuthenticatedUserByDefault(): void
    {
        $sut = $this->createSut();

        self::assertNull($sut->getAuthenticatedUser());
    }

    public function testItGetUserReturnsNullByDefault(): void
    {
        $sut = $this->createSut();

        self::assertNull($sut->getUser());
    }

    // =========================================================================
    // isGuest / isRegisteringUser / isUser / isExternalApp
    // =========================================================================

    public function testItIsGuestReturnsTrueAfterSettingGuest(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);
        $sut->set(WellKnownUserTypes::GUEST);

        self::assertTrue($sut->isGuest());
    }

    public function testItIsUserReturnsTrueAfterSettingUser(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        self::assertTrue($sut->isUser());
        self::assertFalse($sut->isGuest());
        self::assertFalse($sut->isRegisteringUser());
        self::assertFalse($sut->isExternalApp());
    }

    public function testItIsExternalAppReturnsTrueAfterSettingExternalApp(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::EXTERNAL_APP);

        self::assertTrue($sut->isExternalApp());
        self::assertFalse($sut->isGuest());
    }

    public function testItIsRegisteringUserReturnsTrueAfterSettingRegisteringUser(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::REGISTERING_USER);

        self::assertTrue($sut->isRegisteringUser());
        self::assertFalse($sut->isGuest());
    }

    // =========================================================================
    // is / get
    // =========================================================================

    public function testItIsReturnsTrueForCurrentType(): void
    {
        $sut = $this->createSut();
        $sut->set('custom-type');

        self::assertTrue($sut->is('custom-type'));
    }

    public function testItIsReturnsFalseForOtherType(): void
    {
        $sut = $this->createSut();
        $sut->set('custom-type');

        self::assertFalse($sut->is(WellKnownUserTypes::GUEST));
    }

    public function testItGetReturnsCurrentType(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        self::assertSame(WellKnownUserTypes::USER, $sut->get());
    }

    public function testItGetReturnsCustomType(): void
    {
        $sut = $this->createSut();
        $sut->set('my-custom-type');

        self::assertSame('my-custom-type', $sut->get());
    }

    // =========================================================================
    // isCli
    // =========================================================================

    public function testItIsCliReturnsTrueWhenGuestAndCli(): void
    {
        $sut = $this->createSut();

        // In PHPUnit, PHP_SAPI is 'cli', and the default type is GUEST.
        self::assertTrue($sut->isCli());
    }

    public function testItIsCliReturnsFalseWhenNotGuest(): void
    {
        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        self::assertFalse($sut->isCli());
    }

    // =========================================================================
    // set — event dispatching
    // =========================================================================

    public function testItSetDispatchesUserTypeChangedEvent(): void
    {
        Event::fake();

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class);
    }

    public function testItSetDispatchesEventWithCurrentContextInstance(): void
    {
        Event::fake();

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class, static function (UserTypeChangedEvent $event) use ($sut): bool {
            return $event->context === $sut;
        });
    }

    public function testItSetDispatchesEventReflectingNewType(): void
    {
        Event::fake();

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class, static function (UserTypeChangedEvent $event): bool {
            return $event->context->isUser();
        });
    }

    public function testItSetDoesNotDispatchEventWhenTypeUnchanged(): void
    {
        Event::fake();

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::GUEST);

        Event::assertNothingDispatched();
    }

    public function testItSetDispatchesEventOnEveryTypeChange(): void
    {
        Event::fake();

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::USER);
        $sut->set(WellKnownUserTypes::GUEST);

        Event::assertDispatched(UserTypeChangedEvent::class, 2);
    }

    // =========================================================================
    // setRegisteringUser / getRegisteringUser
    // =========================================================================

    public function testItSetRegisteringUserSetsTypeToRegisteringUser(): void
    {
        $sut = $this->createSut();
        $registering = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');
        $sut->setRegisteringUser($registering);

        self::assertTrue($sut->isRegisteringUser());
    }

    public function testItGetRegisteringUserReturnsSetValue(): void
    {
        $sut = $this->createSut();
        $registering = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');
        $sut->setRegisteringUser($registering);

        self::assertSame($registering, $sut->getRegisteringUser());
    }

    public function testItSetRegisteringUserNullResetsTypeToGuest(): void
    {
        $sut = $this->createSut();
        $sut->setRegisteringUser(new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff'));
        $sut->setRegisteringUser(null);

        self::assertTrue($sut->isGuest());
        self::assertNull($sut->getRegisteringUser());
    }

    // =========================================================================
    // getAuthenticatedUser
    // =========================================================================

    public function testItGetAuthenticatedUserReturnsNullWhenNoUserIsAuthenticated(): void
    {
        $sut = $this->createSut();

        self::assertNull($sut->getAuthenticatedUser());
    }

    public function testItGetAuthenticatedUserReturnsUserResolvedThroughGuard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sut = $this->createSut();

        self::assertSame($user, $sut->getAuthenticatedUser());
    }

    public function testItGetAuthenticatedUserIsIndependentOfActiveUserType(): void
    {
        // getAuthenticatedUser() resolves live through the guard; it is not gated on the
        // type token, mirroring how getRegisteringUser() is a plain getter over its own state.
        $user = User::factory()->create();
        $this->actingAs($user);

        $sut = $this->createSut();
        $sut->set(WellKnownUserTypes::GUEST);

        self::assertSame($user, $sut->getAuthenticatedUser());
    }

    // =========================================================================
    // getUser
    // =========================================================================

    public function testItGetUserReturnsAuthenticatedUserWhenNoRegisteringUserIsSet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sut = $this->createSut();

        self::assertSame($user, $sut->getUser());
    }

    public function testItGetUserReturnsRegisteringUserWhenSet(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $sut = $this->createSut();
        $registering = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');
        $sut->setRegisteringUser($registering);

        self::assertSame($registering, $sut->getUser());
    }

    public function testItGetUserReturnsNullWhenNeitherRegisteringNorAuthenticatedUserIsSet(): void
    {
        $sut = $this->createSut();

        self::assertNull($sut->getUser());
    }

    /**
     * Builds a fresh {@see UserContext} wired to the real auth factory from the container,
     * mirroring how the framework constructs the singleton in production.
     */
    private function createSut(): UserContext
    {
        return new UserContext($this->app->make(Factory::class));
    }
}
