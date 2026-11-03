<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Models\Repositories;

use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Identifiers\Values\AiModelIdentifier;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AiModelRepository::class)]
class AiModelRepositoryTest extends TestCase
{
    private AiModelRepository $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new AiModelRepository();
    }

    // =========================================================================
    // whereIdentifier
    // =========================================================================

    public function testItRestrictsAQueryToTheQualifiedIdentifier(): void
    {
        $query = $this->sut->whereIdentifier(
            AiModel::query(),
            AiModelIdentifier::fromString('gwdg/gemma-4-31b-it')
        );

        static::assertSame(['gemma-4-31b-it', 'gwdg'], $query->getBindings());
        $sql = str_replace('`', '', $query->toSql());
        static::assertStringContainsString('model_id = ?', $sql);
        static::assertStringContainsString('provider_id = ?', $sql);
    }

    public function testItMatchesNothingForAnUnqualifiedIdentifier(): void
    {
        $query = $this->sut->whereIdentifier(
            AiModel::query(),
            AiModelIdentifier::fromString('gemma-4-31b-it')
        );

        static::assertSame([], $query->getBindings());
        static::assertStringContainsString('1 = 0', str_replace('`', '', $query->toSql()));
    }
}
