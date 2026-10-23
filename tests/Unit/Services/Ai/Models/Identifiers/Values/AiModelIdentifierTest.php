<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Models\Identifiers\Values;

use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(AiModelIdentifier::class)]
class AiModelIdentifierTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeModel(string $modelId, string|null $providerId): AiModel
    {
        $model = new AiModel(['model_id' => $modelId]);
        $provider = null;
        if ($providerId !== null) {
            $provider = new AiProvider();
            $provider->provider_id = $providerId;
        }
        $model->setRelation('provider', $provider);
        return $model;
    }

    // =========================================================================
    // fromString
    // =========================================================================

    public static function provideFromStringData(): iterable
    {
        yield 'qualified' => ['gwdg/gemma-4-31b-it', 'gwdg', 'gemma-4-31b-it'];
        yield 'plain' => ['gemma-4-31b-it', null, 'gemma-4-31b-it'];
        yield 'model id with separator is split at the first one' => ['openRouter/google/gemma-3', 'openRouter', 'google/gemma-3'];
        yield 'leading separator is plain' => ['/gemma', null, '/gemma'];
        yield 'trailing separator is plain' => ['gwdg/', null, 'gwdg/'];
    }

    #[DataProvider('provideFromStringData')]
    public function testItParsesStrings(string $input, string|null $expectedProvider, string $expectedModel): void
    {
        $sut = AiModelIdentifier::fromString($input);

        static::assertSame($expectedProvider, $sut->providerId);
        static::assertSame($expectedModel, $sut->modelId);
        static::assertSame($expectedProvider !== null, $sut->isQualified());
        static::assertSame($input, $sut->toString());
    }

    // =========================================================================
    // fromModel / toString
    // =========================================================================

    public function testItBuildsQualifiedIdentifierFromModel(): void
    {
        $sut = AiModelIdentifier::fromModel($this->makeModel('gemma-4-31b-it', 'google'));

        static::assertSame('google/gemma-4-31b-it', (string)$sut);
    }

    public function testItFallsBackToPlainModelIdWithoutProvider(): void
    {
        $sut = AiModelIdentifier::fromModel($this->makeModel('gemma-4-31b-it', null));

        static::assertFalse($sut->isQualified());
        static::assertSame('gemma-4-31b-it', (string)$sut);
    }

    public function testAiModelGetIdentifierUsesTheIdentifier(): void
    {
        static::assertSame('gwdg/gemma-4-31b-it', $this->makeModel('gemma-4-31b-it', 'gwdg')->getIdentifier());
    }

    // =========================================================================
    // isNumericReference
    // =========================================================================

    public static function provideIsNumericReferenceData(): iterable
    {
        yield 'integer' => [42, true];
        yield 'numeric string' => ['42', true];
        yield 'zero string' => ['0', true];
        yield 'qualified identifier' => ['gwdg/gemma-4-31b-it', false];
        yield 'plain model id' => ['gemma-4-31b-it', false];
        yield 'model id starting with a digit' => ['4o-mini', false];
    }

    #[DataProvider('provideIsNumericReferenceData')]
    public function testItDetectsNumericReferences(string|int $reference, bool $expected): void
    {
        static::assertSame($expected, AiModelIdentifier::isNumericReference($reference));
    }
}
