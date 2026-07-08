<?php
declare(strict_types=1);

namespace Tests\Unit\Services\System\UsageTypes;

use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;
use App\Services\System\UsageTypes\Events\UsageTypeChangedEvent;
use App\Services\System\UsageTypes\UsageContext;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(UsageContext::class)]
class UsageContextTest extends TestCase
{
    // =========================================================================
    // Defaults
    // =========================================================================

    public function testItIsMainAppByDefault(): void
    {
        $sut = new UsageContext();

        static::assertTrue($sut->isMainApp());
    }

    public function testItIsNotExternalAppByDefault(): void
    {
        $sut = new UsageContext();

        static::assertFalse($sut->isExternalApp());
    }

    public function testItGetReturnsMainAppByDefault(): void
    {
        $sut = new UsageContext();

        static::assertSame(WellKnownUsageTypes::MAIN_APP, $sut->get());
    }

    public function testItIsReturnsTrueForMainAppByDefault(): void
    {
        $sut = new UsageContext();

        static::assertTrue($sut->is(WellKnownUsageTypes::MAIN_APP));
    }

    public function testItIsReturnsFalseForNonDefaultType(): void
    {
        $sut = new UsageContext();

        static::assertFalse($sut->is(WellKnownUsageTypes::EXTERNAL_APP));
    }

    // =========================================================================
    // isMainApp / isExternalApp
    // =========================================================================

    public function testItIsMainAppReturnsTrueAfterSettingMainApp(): void
    {
        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::MAIN_APP);

        static::assertTrue($sut->isMainApp());
        static::assertFalse($sut->isExternalApp());
    }

    public function testItIsExternalAppReturnsTrueAfterSettingExternalApp(): void
    {
        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        static::assertTrue($sut->isExternalApp());
        static::assertFalse($sut->isMainApp());
    }

    // =========================================================================
    // is / get
    // =========================================================================

    public function testItIsReturnsTrueForCurrentType(): void
    {
        $sut = new UsageContext();
        $sut->set('custom-type');

        static::assertTrue($sut->is('custom-type'));
    }

    public function testItIsReturnsFalseForOtherType(): void
    {
        $sut = new UsageContext();
        $sut->set('custom-type');

        static::assertFalse($sut->is(WellKnownUsageTypes::MAIN_APP));
    }

    public function testItGetReturnsCurrentType(): void
    {
        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        static::assertSame(WellKnownUsageTypes::EXTERNAL_APP, $sut->get());
    }

    public function testItGetReturnsCustomType(): void
    {
        $sut = new UsageContext();
        $sut->set('my-custom-type');

        static::assertSame('my-custom-type', $sut->get());
    }

    // =========================================================================
    // set — event dispatching
    // =========================================================================

    public function testItSetDispatchesUsageTypeChangedEvent(): void
    {
        Event::fake();

        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        Event::assertDispatched(UsageTypeChangedEvent::class);
    }

    public function testItSetDispatchesEventWithCurrentContextInstance(): void
    {
        Event::fake();

        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        Event::assertDispatched(UsageTypeChangedEvent::class, function (UsageTypeChangedEvent $event) use ($sut): bool {
            return $event->context === $sut;
        });
    }

    public function testItSetDispatchesEventReflectingNewType(): void
    {
        Event::fake();

        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        Event::assertDispatched(UsageTypeChangedEvent::class, function (UsageTypeChangedEvent $event): bool {
            return $event->context->isExternalApp();
        });
    }

    public function testItSetDispatchesEventOnEveryCall(): void
    {
        Event::fake();

        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);
        $sut->set(WellKnownUsageTypes::MAIN_APP);

        Event::assertDispatched(UsageTypeChangedEvent::class, 2);
    }

    public function testItSetDoesNotDispatchEventWhenTypeIsUnchanged(): void
    {
        Event::fake();

        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::MAIN_APP);

        Event::assertNothingDispatched();
    }

    // =========================================================================
    // getForGiven
    // =========================================================================

    public function testItGetForGivenReturnsStringAsIs(): void
    {
        $sut = new UsageContext();

        static::assertSame('custom-type', $sut->getForGiven('custom-type'));
    }

    public function testItGetForGivenReturnsWellKnownTypeAsIs(): void
    {
        $sut = new UsageContext();

        static::assertSame(WellKnownUsageTypes::EXTERNAL_APP, $sut->getForGiven(WellKnownUsageTypes::EXTERNAL_APP));
    }

    public function testItGetForGivenReturnsCurrentTypeWhenNullPassed(): void
    {
        $sut = new UsageContext();
        $sut->set(WellKnownUsageTypes::EXTERNAL_APP);

        static::assertSame(WellKnownUsageTypes::EXTERNAL_APP, $sut->getForGiven(null));
    }

    public function testItGetForGivenReturnsDefaultTypeWhenNullPassedAndTypeUnchanged(): void
    {
        $sut = new UsageContext();

        static::assertSame(WellKnownUsageTypes::MAIN_APP, $sut->getForGiven(null));
    }
}
