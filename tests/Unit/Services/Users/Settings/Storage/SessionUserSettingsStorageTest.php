<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Contracts\UserSettingsStorageInterface;
use App\Services\Users\Settings\Storage\SessionUserSettingsStorage;
use Illuminate\Contracts\Session\Session;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Covers what the session backend adds on top of
 * {@see \App\Services\Users\Settings\Storage\ArrayUserSettingsStorageTrait}: the
 * mapping of the backing map onto a single session key, and the registration-time
 * promotion. The shared array semantics are covered by
 * {@see RuntimeUserSettingsStorageTest}.
 */
#[CoversClass(SessionUserSettingsStorage::class)]
class SessionUserSettingsStorageTest extends TestCase
{
    private Session $session;
    private SessionUserSettingsStorage $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->session = new Store('test-session', new ArraySessionHandler(60));
        $this->sut = new SessionUserSettingsStorage($this->session);
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(SessionUserSettingsStorage::class, $this->sut);
    }

    // =========================================================================
    // Session mapping
    // =========================================================================

    public function testItStoresEveryNamespaceUnderASingleSessionKey(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('demo-plugin', ['enabled' => '1'], []);

        self::assertSame([
            'hawki-core' => ['theme' => 'dark'],
            'demo-plugin' => ['enabled' => '1'],
        ], $this->session->get('user_settings'));
    }

    public function testItReadsRowsWrittenToTheSessionKeyDirectly(): void
    {
        $this->session->put('user_settings', ['hawki-core' => ['theme' => 'light']]);

        self::assertSame(['theme' => 'light'], $this->sut->loadRaw('hawki-core'));
    }

    public function testItToleratesANonArrayValueUnderTheSessionKey(): void
    {
        // Defensive: a foreign writer (or a stale serialized session) must not fatal
        // the settings layer — it degrades to "nothing stored".
        $this->session->put('user_settings', 'garbage');

        self::assertSame([], $this->sut->loadRaw('hawki-core'));
        self::assertSame([], $this->sut->getNamespaces());
    }

    public function testItGetStorageIdIdentifiesTheSessionBackend(): void
    {
        self::assertSame('session', $this->sut->getStorageId());
    }

    // =========================================================================
    // Promotion
    // =========================================================================

    public function testItPromoteToHandsEveryNamespaceToTheTarget(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('demo-plugin', ['enabled' => '1'], []);

        $target = $this->createMock(UserSettingsStorageInterface::class);

        $received = [];

        $target->method('persist')
            ->willReturnCallback(static function (string $namespace, array $changed, array $removed) use (&$received): void {
                $received[$namespace] = $changed;
            },);

        $this->sut->promoteTo($target);

        self::assertSame([
            'hawki-core' => ['theme' => 'dark'],
            'demo-plugin' => ['enabled' => '1'],
        ], $received);
    }

    public function testItPromoteToClearsTheSessionAfterwards(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);

        $this->sut->promoteTo($this->createMock(UserSettingsStorageInterface::class));

        self::assertSame([], $this->sut->getNamespaces());
        self::assertSame([], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPromoteToCopiesEverythingBeforeClearingAnything(): void
    {
        // An aborted promotion must be able to leave the guest's settings duplicated,
        // never lost — so nothing may be cleared while copies are still outstanding.
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('demo-plugin', ['enabled' => '1'], []);

        $target = $this->createMock(UserSettingsStorageInterface::class);

        $sessionStillIntact = [];

        $target->method('persist')
            ->willReturnCallback(function () use (&$sessionStillIntact): void {
                $sessionStillIntact[] = $this->sut->getNamespaces();
            });

        $this->sut->promoteTo($target);

        self::assertSame([
            ['hawki-core', 'demo-plugin'],
            ['hawki-core', 'demo-plugin'],
        ], $sessionStillIntact);
    }

    public function testItPromoteToTouchesNothingWhenTheSessionIsEmpty(): void
    {
        $target = $this->createMock(UserSettingsStorageInterface::class);
        $target->expects($this->never())->method('persist');

        $this->sut->promoteTo($target);

        self::assertNull($this->session->get('user_settings'));
    }
}
