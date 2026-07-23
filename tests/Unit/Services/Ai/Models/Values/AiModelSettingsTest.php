<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Models\Values;

use App\Services\Ai\Exceptions\InvalidModelSettingException;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AiModelSettings::class)]
#[CoversClass(InvalidModelSettingException::class)]
class AiModelSettingsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeRegistry(array $extra = []): AiModelSettingRegistry
    {
        $registry = new AiModelSettingRegistry();
        $registry->declare(WellKnownModelSettings::TOOL_CALLING, false);
        $registry->declare(WellKnownModelSettings::FILE_UPLOAD, false);
        $registry->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING, 0);
        $registry->declare(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS, 0);
        foreach ($extra as $key => $default) {
            $registry->declare($key, $default);
        }
        return $registry;
    }

    private function makeSettings(array $data = [], ?AiModelSettingRegistry $registry = null): AiModelSettings
    {
        $instance = AiModelSettings::fromArray($data);
        $instance->setService(AiModelSettingRegistry::class, $registry ?? $this->makeRegistry());
        return $instance;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSettings();
        static::assertInstanceOf(AiModelSettings::class, $sut);
    }

    // =========================================================================
    // canUseTools / setUseTools
    // =========================================================================

    public function testItCanUseToolsReturnsFalseByDefault(): void
    {
        $sut = $this->makeSettings();
        static::assertFalse($sut->canUseTools());
    }

    public function testItCanUseToolsReturnsTrueWhenEnabled(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::TOOL_CALLING => true]);
        static::assertTrue($sut->canUseTools());
    }

    public function testItSetUseToolsEnablesToolCalling(): void
    {
        $sut = $this->makeSettings();
        $sut->setUseTools(true);
        static::assertTrue($sut->canUseTools());
    }

    public function testItSetUseToolsReturnsSelf(): void
    {
        $sut = $this->makeSettings();
        static::assertSame($sut, $sut->setUseTools(true));
    }

    // =========================================================================
    // canHandleFiles / setHandleFiles
    // =========================================================================

    public function testItCanHandleFilesReturnsFalseByDefault(): void
    {
        $sut = $this->makeSettings();
        static::assertFalse($sut->canHandleFiles());
    }

    public function testItCanHandleFilesReturnsTrueWhenEnabled(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::FILE_UPLOAD => true]);
        static::assertTrue($sut->canHandleFiles());
    }

    public function testItSetHandleFilesEnablesFileUpload(): void
    {
        $sut = $this->makeSettings();
        $sut->setHandleFiles(true);
        static::assertTrue($sut->canHandleFiles());
    }

    public function testItSetHandleFilesReturnsSelf(): void
    {
        $sut = $this->makeSettings();
        static::assertSame($sut, $sut->setHandleFiles(true));
    }

    // =========================================================================
    // getMaxToolCallingRounds / setMaxToolCallingRounds
    // =========================================================================

    public function testItGetMaxToolCallingRoundsStreamingDefaultsToZero(): void
    {
        $sut = $this->makeSettings();
        static::assertSame(0, $sut->getMaxToolCallingRounds(true));
    }

    public function testItGetMaxToolCallingRoundsNonStreamingDefaultsToZero(): void
    {
        $sut = $this->makeSettings();
        static::assertSame(0, $sut->getMaxToolCallingRounds(false));
    }

    public function testItGetMaxToolCallingRoundsStreamingReturnsConfiguredValue(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING => 5]);
        static::assertSame(5, $sut->getMaxToolCallingRounds(true));
    }

    public function testItGetMaxToolCallingRoundsNonStreamingReturnsConfiguredValue(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => 3]);
        static::assertSame(3, $sut->getMaxToolCallingRounds(false));
    }

    public function testItSetMaxToolCallingRoundsStreamingStoresValue(): void
    {
        $sut = $this->makeSettings();
        $sut->setMaxToolCallingRounds(7);
        static::assertSame(7, $sut->getMaxToolCallingRounds(true));
    }

    public function testItSetMaxToolCallingRoundsNonStreamingStoresValue(): void
    {
        $sut = $this->makeSettings();
        $sut->setMaxToolCallingRounds(4, false);
        static::assertSame(4, $sut->getMaxToolCallingRounds(false));
    }

    public function testItSetMaxToolCallingRoundsDoesNotAffectOtherVariant(): void
    {
        $sut = $this->makeSettings();
        $sut->setMaxToolCallingRounds(10, true);
        static::assertSame(0, $sut->getMaxToolCallingRounds(false));
    }

    public function testItSetMaxToolCallingRoundsReturnsSelf(): void
    {
        $sut = $this->makeSettings();
        static::assertSame($sut, $sut->setMaxToolCallingRounds(5));
    }

    // =========================================================================
    // has
    // =========================================================================

    public function testItHasReturnsTrueForExplicitlySetKey(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::TOOL_CALLING => true]);
        static::assertTrue($sut->has(WellKnownModelSettings::TOOL_CALLING));
    }

    public function testItHasReturnsFalseForKeyNotExplicitlySet(): void
    {
        $sut = $this->makeSettings();
        static::assertFalse($sut->has(WellKnownModelSettings::TOOL_CALLING));
    }

    public function testItHasWithIncludeDefaultReturnsTrueForDeclaredRegistryKey(): void
    {
        // Key is declared in the registry but not explicitly set on the model
        $sut = $this->makeSettings();
        static::assertTrue($sut->has(WellKnownModelSettings::TOOL_CALLING, true));
    }

    public function testItHasWithIncludeDefaultReturnsFalseForUndeclaredKey(): void
    {
        $sut = $this->makeSettings();
        static::assertFalse($sut->has('undeclared_custom_key', true));
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testItGetReturnsExplicitlySetValue(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::TOOL_CALLING => true]);
        static::assertTrue($sut->get(WellKnownModelSettings::TOOL_CALLING));
    }

    public function testItGetReturnsRegistryDefaultWhenKeyNotExplicitlySet(): void
    {
        // Registry declares TOOL_CALLING with a default of false; no explicit value on the model
        $sut = $this->makeSettings();
        static::assertFalse($sut->get(WellKnownModelSettings::TOOL_CALLING));
    }

    public function testItGetReturnsProvidedDefaultWhenKeyNotExplicitlySet(): void
    {
        $sut = $this->makeSettings();
        static::assertSame('fallback', $sut->get('undeclared_key', 'fallback'));
    }

    public function testItGetReturnsNullWhenKeyAbsentAndNoDefaultGiven(): void
    {
        $sut = $this->makeSettings();
        static::assertNull($sut->get('undeclared_key'));
    }

    // =========================================================================
    // set
    // =========================================================================

    public function testItSetStoresValue(): void
    {
        $sut = $this->makeSettings();
        $sut->set(WellKnownModelSettings::TOOL_CALLING, true);
        static::assertTrue($sut->get(WellKnownModelSettings::TOOL_CALLING));
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = $this->makeSettings();
        static::assertSame($sut, $sut->set(WellKnownModelSettings::TOOL_CALLING, true));
    }

    public function testItSetThrowsForUndeclaredKey(): void
    {
        $sut = $this->makeSettings();
        $this->expectException(InvalidModelSettingException::class);
        $this->expectExceptionMessage(sprintf('Setting "%s" is not declared in the ModelSettingRegistry.', 'unknown_key'));
        $sut->set('unknown_key', 'value');
    }

    public function testItSetClearsExplicitEntryWhenValueMatchesCurrentlyStoredValue(): void
    {
        // When a value that is already explicitly stored is set again to the same value,
        // the explicit entry is removed so reads fall back to the registry default.
        $sut = $this->makeSettings([WellKnownModelSettings::TOOL_CALLING => true]);
        $sut->set(WellKnownModelSettings::TOOL_CALLING, true);
        static::assertSame([], $sut->toArray());
    }

    // =========================================================================
    // fromArray / toArray / jsonSerialize
    // =========================================================================

    public function testItFromArrayCreatesInstance(): void
    {
        $sut = AiModelSettings::fromArray([]);
        $sut->setService(AiModelSettingRegistry::class, $this->makeRegistry());
        static::assertInstanceOf(AiModelSettings::class, $sut);
    }

    public function testItToArrayReturnsExplicitSettings(): void
    {
        $data = [
            WellKnownModelSettings::TOOL_CALLING => true,
            WellKnownModelSettings::FILE_UPLOAD => true,
        ];
        $sut = $this->makeSettings($data);
        static::assertSame($data, $sut->toArray());
    }

    public function testItToArrayIsEmptyWhenNoExplicitSettingsAreSet(): void
    {
        $sut = $this->makeSettings();
        static::assertSame([], $sut->toArray());
    }

    public function testItJsonSerializesAlwaysIncludesWellKnownSettings(): void
    {
        $sut = $this->makeSettings([WellKnownModelSettings::TOOL_CALLING => true]);
        $serialized = $sut->jsonSerialize();
        static::assertTrue($serialized[WellKnownModelSettings::TOOL_CALLING]);
        static::assertFalse($serialized[WellKnownModelSettings::FILE_UPLOAD]);
        static::assertSame(0, $serialized[WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS]);
        static::assertSame(0, $serialized[WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING]);
    }

    public function testItJsonSerializesViaJsonEncode(): void
    {
        $sut = $this->makeSettings([
            WellKnownModelSettings::TOOL_CALLING => true,
            WellKnownModelSettings::FILE_UPLOAD => false,
        ]);
        $expected = [
            WellKnownModelSettings::TOOL_CALLING => true,
            WellKnownModelSettings::FILE_UPLOAD => false,
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => 0,
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING => 0,
        ];
        static::assertSame(json_encode($expected), json_encode($sut));
    }

    public function testItRoundtripsFromArrayToArray(): void
    {
        $data = [
            WellKnownModelSettings::TOOL_CALLING => true,
            WellKnownModelSettings::FILE_UPLOAD => false,
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS_STREAMING => 5,
        ];
        $sut = $this->makeSettings($data);
        static::assertSame($data, $sut->toArray());
    }
}
