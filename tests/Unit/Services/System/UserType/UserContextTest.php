<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\UserType;

use App\Services\System\UserTypes\Contracts\WellKnownUserTypes;
use App\Services\System\UserTypes\Events\UserTypeChangedEvent;
use App\Services\System\UserTypes\UserContext;
use App\Services\System\UserTypes\Values\RegisteringUser;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UserContext::class)]
class UserContextTest extends TestCase
{
    // =========================================================================
    // Defaults
    // =========================================================================

    public function testItIsGuestByDefault(): void
    {
        $sut = new UserContext();

        static::assertTrue($sut->isGuest());
    }

    public function testItIsNotRegisteringUserByDefault(): void
    {
        $sut = new UserContext();

        static::assertFalse($sut->isRegisteringUser());
    }

    public function testItIsNotUserByDefault(): void
    {
        $sut = new UserContext();

        static::assertFalse($sut->isUser());
    }

    public function testItIsNotExternalAppByDefault(): void
    {
        $sut = new UserContext();

        static::assertFalse($sut->isExternalApp());
    }

    public function testItGetReturnsGuestByDefault(): void
    {
        $sut = new UserContext();

        static::assertSame(WellKnownUserTypes::GUEST, $sut->get());
    }

    public function testItIsReturnsTrueForGuestByDefault(): void
    {
        $sut = new UserContext();

        static::assertTrue($sut->is(WellKnownUserTypes::GUEST));
    }

    public function testItIsReturnsFalseForNonDefaultType(): void
    {
        $sut = new UserContext();

        static::assertFalse($sut->is(WellKnownUserTypes::USER));
    }

    public function testItHasNoRegisteringUserByDefault(): void
    {
        $sut = new UserContext();

        static::assertNull($sut->getRegisteringUser());
    }

    // =========================================================================
    // isGuest / isRegisteringUser / isUser / isExternalApp
    // =========================================================================

    public function testItIsGuestReturnsTrueAfterSettingGuest(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);
        $sut->set(WellKnownUserTypes::GUEST);

        static::assertTrue($sut->isGuest());
    }

    public function testItIsUserReturnsTrueAfterSettingUser(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        static::assertTrue($sut->isUser());
        static::assertFalse($sut->isGuest());
        static::assertFalse($sut->isRegisteringUser());
        static::assertFalse($sut->isExternalApp());
    }

    public function testItIsExternalAppReturnsTrueAfterSettingExternalApp(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::EXTERNAL_APP);

        static::assertTrue($sut->isExternalApp());
        static::assertFalse($sut->isGuest());
    }

    public function testItIsRegisteringUserReturnsTrueAfterSettingRegisteringUser(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::REGISTERING_USER);

        static::assertTrue($sut->isRegisteringUser());
        static::assertFalse($sut->isGuest());
    }

    // =========================================================================
    // is / get
    // =========================================================================

    public function testItIsReturnsTrueForCurrentType(): void
    {
        $sut = new UserContext();
        $sut->set('custom-type');

        static::assertTrue($sut->is('custom-type'));
    }

    public function testItIsReturnsFalseForOtherType(): void
    {
        $sut = new UserContext();
        $sut->set('custom-type');

        static::assertFalse($sut->is(WellKnownUserTypes::GUEST));
    }

    public function testItGetReturnsCurrentType(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        static::assertSame(WellKnownUserTypes::USER, $sut->get());
    }

    public function testItGetReturnsCustomType(): void
    {
        $sut = new UserContext();
        $sut->set('my-custom-type');

        static::assertSame('my-custom-type', $sut->get());
    }

    // =========================================================================
    // isCli
    // =========================================================================

    public function testItIsCliReturnsTrueWhenGuestAndCli(): void
    {
        $sut = new UserContext();

        // In PHPUnit, PHP_SAPI is 'cli', and the default type is GUEST.
        static::assertTrue($sut->isCli());
    }

    public function testItIsCliReturnsFalseWhenNotGuest(): void
    {
        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        static::assertFalse($sut->isCli());
    }

    // =========================================================================
    // set — event dispatching
    // =========================================================================

    public function testItSetDispatchesUserTypeChangedEvent(): void
    {
        Event::fake();

        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class);
    }

    public function testItSetDispatchesEventWithCurrentContextInstance(): void
    {
        Event::fake();

        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class, function (UserTypeChangedEvent $event) use ($sut): bool {
            return $event->context === $sut;
        });
    }

    public function testItSetDispatchesEventReflectingNewType(): void
    {
        Event::fake();

        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);

        Event::assertDispatched(UserTypeChangedEvent::class, function (UserTypeChangedEvent $event): bool {
            return $event->context->isUser();
        });
    }

    public function testItSetDoesNotDispatchEventWhenTypeUnchanged(): void
    {
        Event::fake();

        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::GUEST);

        Event::assertNothingDispatched();
    }

    public function testItSetDispatchesEventOnEveryTypeChange(): void
    {
        Event::fake();

        $sut = new UserContext();
        $sut->set(WellKnownUserTypes::USER);
        $sut->set(WellKnownUserTypes::GUEST);

        Event::assertDispatched(UserTypeChangedEvent::class, 2);
    }

    // =========================================================================
    // setRegisteringUser / getRegisteringUser
    // =========================================================================

    public function testItSetRegisteringUserSetsTypeToRegisteringUser(): void
    {
        $sut = new UserContext();
        $registering = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');
        $sut->setRegisteringUser($registering);

        static::assertTrue($sut->isRegisteringUser());
    }

    public function testItGetRegisteringUserReturnsSetValue(): void
    {
        $sut = new UserContext();
        $registering = new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff');
        $sut->setRegisteringUser($registering);

        static::assertSame($registering, $sut->getRegisteringUser());
    }

    public function testItSetRegisteringUserNullResetsTypeToGuest(): void
    {
        $sut = new UserContext();
        $sut->setRegisteringUser(new RegisteringUser('jdoe', 'John Doe', 'jdoe@example.com', 'staff'));
        $sut->setRegisteringUser(null);

        static::assertTrue($sut->isGuest());
        static::assertNull($sut->getRegisteringUser());
    }
}
