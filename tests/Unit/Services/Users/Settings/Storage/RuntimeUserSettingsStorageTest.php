<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Users\Settings\Storage;

use App\Services\Users\Settings\Storage\ArrayUserSettingsStorageTrait;
use App\Services\Users\Settings\Storage\RuntimeUserSettingsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use Tests\TestCase;

/**
 * Covers the shared array semantics of {@see ArrayUserSettingsStorageTrait} through
 * its simplest concrete backend — the session storage inherits the exact same
 * behaviour and only swaps the medium the map is read from and written to.
 */
#[CoversClass(RuntimeUserSettingsStorage::class)]
#[CoversTrait(ArrayUserSettingsStorageTrait::class)]
class RuntimeUserSettingsStorageTest extends TestCase
{
    private RuntimeUserSettingsStorage $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new RuntimeUserSettingsStorage();
    }

    // =========================================================================
    // testItConstructs
    // =========================================================================

    public function testItConstructs(): void
    {
        self::assertInstanceOf(RuntimeUserSettingsStorage::class, $this->sut);
    }

    // =========================================================================
    // Reading
    // =========================================================================

    public function testItLoadRawReturnsAnEmptyArrayForUnknownNamespaces(): void
    {
        self::assertSame([], $this->sut->loadRaw('hawki-core'));
    }

    public function testItGetStorageIdIdentifiesTheRuntimeBackend(): void
    {
        self::assertSame('runtime', $this->sut->getStorageId());
    }

    // =========================================================================
    // Writing
    // =========================================================================

    public function testItPersistMergesIntoTheExistingRows(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark', 'timezone' => 'UTC'], []);
        $this->sut->persist('hawki-core', ['theme' => 'light'], []);

        self::assertSame(['theme' => 'light', 'timezone' => 'UTC'], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPersistKeepsNamespacesApart(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('demo-plugin', ['theme' => 'light'], []);

        self::assertSame(['theme' => 'dark'], $this->sut->loadRaw('hawki-core'));
        self::assertSame(['theme' => 'light'], $this->sut->loadRaw('demo-plugin'));
    }

    public function testItPersistStoresNullValues(): void
    {
        $this->sut->persist('hawki-core', ['locale' => null], []);

        self::assertSame(['locale' => null], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPersistAppliesUpsertsAndRemovalsInOneCall(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark', 'timezone' => 'UTC'], []);
        $this->sut->persist('hawki-core', ['timezone' => 'Europe/Berlin'], ['theme']);

        self::assertSame(['timezone' => 'Europe/Berlin'], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPersistIsANoOpWhenNothingChanged(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('hawki-core', [], []);

        self::assertSame(['theme' => 'dark'], $this->sut->loadRaw('hawki-core'));
        self::assertSame(['hawki-core'], $this->sut->getNamespaces());
    }

    // =========================================================================
    // Removing
    // =========================================================================

    public function testItPersistRemovesOnlyTheGivenKeys(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark', 'timezone' => 'UTC'], []);
        $this->sut->persist('hawki-core', [], ['theme']);

        self::assertSame(['timezone' => 'UTC'], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPersistIgnoresRemovalsOfKeysThatAreNotStored(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('hawki-core', [], ['timezone']);

        self::assertSame(['theme' => 'dark'], $this->sut->loadRaw('hawki-core'));
    }

    public function testItPersistDropsTheNamespaceOnceItsLastRowIsGone(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('hawki-core', [], ['theme']);

        self::assertSame([], $this->sut->getNamespaces());
    }

    public function testItPersistDoesNotMaterializeAnUnknownNamespace(): void
    {
        $this->sut->persist('hawki-core', [], ['theme']);

        self::assertSame([], $this->sut->getNamespaces());
    }

    // =========================================================================
    // Namespaces
    // =========================================================================

    public function testItGetNamespacesReturnsEveryNamespaceWithRows(): void
    {
        $this->sut->persist('hawki-core', ['theme' => 'dark'], []);
        $this->sut->persist('demo-plugin', ['enabled' => '1'], []);

        self::assertSame(['hawki-core', 'demo-plugin'], $this->sut->getNamespaces());
    }
}
