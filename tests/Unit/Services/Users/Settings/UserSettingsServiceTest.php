<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings;

use App\Models\User;
use App\Services\System\Container\SystemEnvironment;
use App\Services\System\Database\SettingsAndConfig\Values\SettingsValueComparator;
use App\Services\System\UserTypes\UserContext;
use App\Services\Users\Exceptions\InvalidUserSettingsClassException;
use App\Services\Users\Settings\CoreUserSettings;
use App\Services\Users\Settings\Storage\DatabaseUserSettingsStorage;
use App\Services\Users\Settings\Storage\RuntimeUserSettingsStorage;
use App\Services\Users\Settings\Storage\SessionUserSettingsStorage;
use App\Services\Users\Settings\UserSettingsService;
use App\Services\Users\Settings\Values\Theme;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

#[CoversClass(UserSettingsService::class)]
class UserSettingsServiceTest extends TestCase
{
    private UserSettingsService $sut;
    private MockObject&UserContext $userContext;
    private MockObject&SystemEnvironment $systemEnvironment;
    private DatabaseUserSettingsStorage&MockObject $databaseStorage;
    private MockObject&SessionUserSettingsStorage $sessionStorage;
    private MockObject&RuntimeUserSettingsStorage $runtimeStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->userContext = $this->createMock(UserContext::class);
        $this->systemEnvironment = $this->createMock(SystemEnvironment::class);
        $this->databaseStorage = $this->createMock(DatabaseUserSettingsStorage::class);
        $this->sessionStorage = $this->createMock(SessionUserSettingsStorage::class);
        $this->runtimeStorage = $this->createMock(RuntimeUserSettingsStorage::class);

        $this->sut = new UserSettingsService(
            $this->userContext,
            $this->systemEnvironment,
            $this->databaseStorage,
            $this->sessionStorage,
            $this->runtimeStorage,
            new SettingsValueComparator(),
        );
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(UserSettingsService::class, $this->sut);
    }

    // =========================================================================
    // Storage selection
    // =========================================================================

    public function testItGetUsesTheDatabaseStorageForAuthenticatedUsers(): void
    {
        $this->givenUser(42);

        $this->databaseStorage->method('getStorageId')->willReturn('database:42');
        $this->databaseStorage->method('loadRaw')->willReturn(['theme' => 'dark']);

        $settings = $this->sut->get(CoreUserSettings::class);

        self::assertSame(Theme::Dark, $settings->theme);
    }

    public function testItGetUsesTheSessionStorageForGuestsOutsideCli(): void
    {
        $this->givenGuest();
        $this->systemEnvironment->method('runningInConsole')->willReturn(false);

        $this->sessionStorage->method('getStorageId')->willReturn('session');
        $this->sessionStorage->method('loadRaw')->willReturn(['timezone' => 'Europe/Berlin']);

        $settings = $this->sut->get(CoreUserSettings::class);

        self::assertSame('Europe/Berlin', $settings->timezone);
    }

    public function testItGetUsesTheRuntimeStorageForGuestsInCli(): void
    {
        $this->givenGuest();
        $this->systemEnvironment->method('runningInConsole')->willReturn(true);

        $this->runtimeStorage->method('getStorageId')->willReturn('runtime');
        $this->runtimeStorage->method('loadRaw')->willReturn([]);

        $settings = $this->sut->get(CoreUserSettings::class);

        self::assertSame(Theme::Auto, $settings->theme);
    }

    // =========================================================================
    // Hydration
    // =========================================================================

    public function testItGetHydratesDefaultsForMissingRows(): void
    {
        $this->givenGuestInCliWithRawRows([]);

        $settings = $this->sut->get(CoreUserSettings::class);

        self::assertSame(Theme::Auto, $settings->theme);
        self::assertNull($settings->locale);
        self::assertSame('UTC', $settings->timezone);
    }

    public function testItGetThrowsForNonSettingsClasses(): void
    {
        $this->expectException(InvalidUserSettingsClassException::class);
        $this->expectExceptionMessage(\sprintf(
            'The class "%s" must extend "%s" to be loadable via the user-settings service.',
            \stdClass::class,
            \App\Services\Users\Settings\AbstractUserSettings::class,
        ));

        $this->sut->get(\stdClass::class);
    }

    // =========================================================================
    // Identity map
    // =========================================================================

    public function testItGetCachesPerClassAndStorage(): void
    {
        $this->givenGuestInCliWithRawRows([]);

        self::assertSame(
            $this->sut->get(CoreUserSettings::class),
            $this->sut->get(CoreUserSettings::class),
        );
    }

    public function testItGetReturnsAFreshInstanceAfterAUserSwitch(): void
    {
        // User 42 loads first, then the user switches to 43 within the same process —
        // the identity map must never leak the first user's instance.
        $this->givenUser(42);
        $this->databaseStorage->method('getStorageId')
            ->willReturnOnConsecutiveCalls('database:42', 'database:43', 'database:42');
        $this->databaseStorage->method('loadRaw')->willReturn([]);

        $first = $this->sut->get(CoreUserSettings::class);

        $this->givenUser(43);

        $second = $this->sut->get(CoreUserSettings::class);

        self::assertNotSame($first, $second);

        // Switching back yields the original, still intact instance.
        self::assertSame($first, $this->sut->get(CoreUserSettings::class));
    }

    // =========================================================================
    // Diff-based save
    // =========================================================================

    public function testItSavePersistsOnlyTheChangedKeys(): void
    {
        $this->givenGuestInCliWithRawRows([]);

        $settings = $this->sut->get(CoreUserSettings::class);
        $settings->theme = Theme::Dark;

        $this->runtimeStorage->expects($this->once())
            ->method('persistChanged')
            ->with('hawki-core', ['theme' => 'dark']);

        $this->sut->save($settings);
    }

    public function testItSaveRemovesKeysThatRevertedToTheirDefaults(): void
    {
        // A stored row exists for theme (customized to dark); the user reverts it to
        // the default — the row must be deleted (sparse storage).
        $this->givenGuestInCliWithRawRows(['theme' => 'dark']);

        $settings = $this->sut->get(CoreUserSettings::class);
        $settings->theme = Theme::Auto;

        $this->runtimeStorage->expects($this->once())
            ->method('persistChanged')
            ->with('hawki-core', []);

        $this->runtimeStorage->expects($this->once())
            ->method('removeKeys')
            ->with('hawki-core', ['theme']);

        $this->sut->save($settings);
    }

    public function testItSaveRemovesStaleRowsOfRemovedProperties(): void
    {
        // A stored row for a key the class no longer declares is garbage — removed.
        $this->givenGuestInCliWithRawRows(['removed_key' => 'legacy']);

        $settings = $this->sut->get(CoreUserSettings::class);

        $this->runtimeStorage->expects($this->once())
            ->method('removeKeys')
            ->with('hawki-core', ['removed_key']);

        $this->sut->save($settings);
    }

    public function testItSaveRewritesStillCustomizedKeysAndRemovesRevertedOnes(): void
    {
        // timezone stays customized (rewritten idempotently), theme reverts to its
        // default (row deleted). Untouched-by-diff properties never reach storage.
        $this->givenGuestInCliWithRawRows(['timezone' => 'Europe/Berlin', 'theme' => 'dark']);

        $settings = $this->sut->get(CoreUserSettings::class);
        $settings->theme = Theme::Auto;

        $this->runtimeStorage->expects($this->once())
            ->method('persistChanged')
            ->with('hawki-core', ['timezone' => 'Europe/Berlin']);

        $this->runtimeStorage->expects($this->once())
            ->method('removeKeys')
            ->with('hawki-core', ['theme']);

        $this->sut->save($settings);
    }

    public function testItSaveUpdatesTheIdentityMap(): void
    {
        $this->givenGuestInCliWithRawRows([]);

        $settings = $this->sut->get(CoreUserSettings::class);
        $settings->timezone = 'Europe/Berlin';

        $this->sut->save($settings);

        self::assertSame($settings, $this->sut->get(CoreUserSettings::class));
    }

    // =========================================================================
    // Fixtures
    // =========================================================================

    private function givenUser(int $id): void
    {
        $user = new User();
        $user->setAttribute('id', $id);

        $this->userContext->method('getUser')->willReturn($user);
    }

    private function givenGuest(): void
    {
        $this->userContext->method('getUser')->willReturn(null);
    }

    private function givenGuestInCliWithRawRows(array $rawRows): void
    {
        $this->givenGuest();
        $this->systemEnvironment->method('runningInConsole')->willReturn(true);
        $this->runtimeStorage->method('getStorageId')->willReturn('runtime');
        $this->runtimeStorage->method('loadRaw')->willReturn($rawRows);
    }
}
