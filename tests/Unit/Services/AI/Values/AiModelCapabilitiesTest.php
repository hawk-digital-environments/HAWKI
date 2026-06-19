<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Values;

use App\Services\Ai\Exceptions\InvalidModelCapabilityException;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use App\Services\Ai\Values\ModelCapabilities;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\WellKnownCapabilities;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(ModelCapabilities::class)]
#[CoversClass(InvalidModelCapabilityException::class)]
class AiModelCapabilitiesTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeRegistry(array $extra = []): AiModelCapabilityRegistry
    {
        $registry = new AiModelCapabilityRegistry();
        $registry->declare(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::NO);
        $registry->declare(WellKnownCapabilities::KNOWLEDGE_BASE, ModelCapabilityValueType::NO);
        foreach ($extra as $key => $default) {
            $registry->declare($key, $default);
        }
        return $registry;
    }

    private function makeCapabilities(array $data = [], ?AiModelCapabilityRegistry $registry = null): ModelCapabilities
    {
        return ModelCapabilities::fromArray($data, $registry ?? $this->makeRegistry());
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeCapabilities();
        static::assertInstanceOf(ModelCapabilities::class, $sut);
    }

    // =========================================================================
    // canUseWebSearch / getWebSearch / setWebSearch
    // =========================================================================

    public function testItCanUseWebSearchReturnsFalseByDefault(): void
    {
        $sut = $this->makeCapabilities();
        static::assertFalse($sut->canUseWebSearch());
    }

    public function testItCanUseWebSearchReturnsTrueWhenSetToYes(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value]);
        static::assertTrue($sut->canUseWebSearch());
    }

    public function testItCanUseWebSearchReturnsTrueWhenSetToNative(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::NATIVE->value]);
        static::assertTrue($sut->canUseWebSearch());
    }

    public function testItCanUseWebSearchReturnsTrueWhenSetToTool(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::TOOL->value]);
        static::assertTrue($sut->canUseWebSearch());
    }

    public function testItGetWebSearchReturnsNoByDefault(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame(ModelCapabilityValueType::NO, $sut->getWebSearch());
    }

    public function testItGetWebSearchReturnsExplicitlySetValue(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::NATIVE->value]);
        static::assertSame(ModelCapabilityValueType::NATIVE, $sut->getWebSearch());
    }

    public function testItSetWebSearchStoresValue(): void
    {
        $sut = $this->makeCapabilities();
        $sut->setWebSearch(ModelCapabilityValueType::YES);
        static::assertSame(ModelCapabilityValueType::YES, $sut->getWebSearch());
    }

    public function testItSetWebSearchReturnsSelf(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame($sut, $sut->setWebSearch(ModelCapabilityValueType::YES));
    }

    // =========================================================================
    // canUseKnowledgeBase / getKnowledgeBase / setKnowledgeBase
    // =========================================================================

    public function testItCanUseKnowledgeBaseReturnsFalseByDefault(): void
    {
        $sut = $this->makeCapabilities();
        static::assertFalse($sut->canUseKnowledgeBase());
    }

    public function testItCanUseKnowledgeBaseReturnsTrueWhenEnabled(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::KNOWLEDGE_BASE => ModelCapabilityValueType::TOOL->value]);
        static::assertTrue($sut->canUseKnowledgeBase());
    }

    public function testItGetKnowledgeBaseReturnsNoByDefault(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame(ModelCapabilityValueType::NO, $sut->getKnowledgeBase());
    }

    public function testItGetKnowledgeBaseReturnsExplicitlySetValue(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::KNOWLEDGE_BASE => ModelCapabilityValueType::TOOL->value]);
        static::assertSame(ModelCapabilityValueType::TOOL, $sut->getKnowledgeBase());
    }

    public function testItSetKnowledgeBaseStoresValue(): void
    {
        $sut = $this->makeCapabilities();
        $sut->setKnowledgeBase(ModelCapabilityValueType::YES);
        static::assertSame(ModelCapabilityValueType::YES, $sut->getKnowledgeBase());
    }

    public function testItSetKnowledgeBaseReturnsSelf(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame($sut, $sut->setKnowledgeBase(ModelCapabilityValueType::YES));
    }

    // =========================================================================
    // canUse
    // =========================================================================

    public function testItCanUseReturnsFalseWhenValueIsNo(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::NO->value]);
        static::assertFalse($sut->canUse(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItCanUseReturnsFalseWhenKeyAbsentAndRegistryDefaultIsNo(): void
    {
        $sut = $this->makeCapabilities();
        static::assertFalse($sut->canUse(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItCanUseReturnsTrueWhenValueIsYes(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value]);
        static::assertTrue($sut->canUse(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItCanUseReturnsTrueWhenRegistryDefaultIsNotNo(): void
    {
        $registry = $this->makeRegistry(['plugin.feature' => ModelCapabilityValueType::YES]);
        $sut = $this->makeCapabilities([], $registry);
        static::assertTrue($sut->canUse('plugin.feature'));
    }

    // =========================================================================
    // get
    // =========================================================================

    public function testItGetReturnsExplicitlySetValue(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::NATIVE->value]);
        static::assertSame(ModelCapabilityValueType::NATIVE, $sut->get(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItGetReturnsRegistryDefaultWhenKeyNotExplicitlySet(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame(ModelCapabilityValueType::NO, $sut->get(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItGetReturnsProvidedDefaultWhenKeyIsUndeclared(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame(ModelCapabilityValueType::TOOL, $sut->get('undeclared_key', ModelCapabilityValueType::TOOL));
    }

    public function testItGetReturnsNullWhenKeyAbsentAndNoDefaultGiven(): void
    {
        $sut = $this->makeCapabilities();
        static::assertNull($sut->get('undeclared_key'));
    }

    // =========================================================================
    // set
    // =========================================================================

    public function testItSetStoresValue(): void
    {
        $sut = $this->makeCapabilities();
        $sut->set(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::YES);
        static::assertSame(ModelCapabilityValueType::YES, $sut->get(WellKnownCapabilities::WEB_SEARCH));
    }

    public function testItSetReturnsSelf(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame($sut, $sut->set(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::YES));
    }

    public function testItSetThrowsForUndeclaredKey(): void
    {
        $sut = $this->makeCapabilities();
        $this->expectException(InvalidModelCapabilityException::class);
        $this->expectExceptionMessage('Capability "unknown_key" is not declared in the ModelCapabilityRegistry.');
        $sut->set('unknown_key', ModelCapabilityValueType::YES);
    }

    public function testItSetClearsExplicitEntryWhenValueMatchesRegistryDefault(): void
    {
        // Registry default for WEB_SEARCH is NO; setting it to NO must clear the explicit entry.
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value]);
        $sut->set(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::NO);
        static::assertSame([], $sut->toArray());
    }

    public function testItSetDoesNotStoreEntryWhenValueAlreadyMatchesRegistryDefault(): void
    {
        // Setting a value equal to the registry default on a fresh instance should not add an explicit entry.
        $sut = $this->makeCapabilities();
        $sut->set(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::NO);
        static::assertSame([], $sut->toArray());
    }

    // =========================================================================
    // fromArray
    // =========================================================================

    public function testItFromArrayCreatesInstance(): void
    {
        $sut = ModelCapabilities::fromArray([], $this->makeRegistry());
        static::assertInstanceOf(ModelCapabilities::class, $sut);
    }

    public function testItFromArraySilentlyDropsInvalidValues(): void
    {
        $sut = $this->makeCapabilities([
            WellKnownCapabilities::WEB_SEARCH => 'invalid_value',
            WellKnownCapabilities::KNOWLEDGE_BASE => ModelCapabilityValueType::YES->value,
        ]);
        static::assertSame([WellKnownCapabilities::KNOWLEDGE_BASE => ModelCapabilityValueType::YES->value], $sut->toArray());
    }

    public function testItFromArraySilentlyDropsNullValues(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => null]);
        static::assertSame([], $sut->toArray());
    }

    // =========================================================================
    // toArray / jsonSerialize
    // =========================================================================

    public function testItToArrayReturnsOnlyExplicitlySetCapabilities(): void
    {
        $data = [WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value];
        $sut = $this->makeCapabilities($data);
        static::assertSame($data, $sut->toArray());
    }

    public function testItToArrayIsEmptyWhenNoExplicitCapabilitiesAreSet(): void
    {
        $sut = $this->makeCapabilities();
        static::assertSame([], $sut->toArray());
    }

    public function testItToArraySerializesEnumValuesToStrings(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::NATIVE->value]);
        $result = $sut->toArray();
        static::assertSame('native', $result[WellKnownCapabilities::WEB_SEARCH]);
    }

    public function testItJsonSerializesMatchesToArray(): void
    {
        $sut = $this->makeCapabilities([WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value]);
        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }

    public function testItJsonSerializesViaJsonEncode(): void
    {
        $data = [WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::TOOL->value];
        $sut = $this->makeCapabilities($data);
        static::assertSame(json_encode($data), json_encode($sut));
    }

    // =========================================================================
    // Roundtrip
    // =========================================================================

    public function testItRoundtripsFromArrayToArray(): void
    {
        $data = [
            WellKnownCapabilities::WEB_SEARCH => ModelCapabilityValueType::YES->value,
            WellKnownCapabilities::KNOWLEDGE_BASE => ModelCapabilityValueType::TOOL->value,
        ];
        $sut = $this->makeCapabilities($data);
        static::assertSame($data, $sut->toArray());
    }
}
