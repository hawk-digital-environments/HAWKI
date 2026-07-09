<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\StatusCheck;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(AiModelDemandCollection::class)]
class AiModelDemandCollectionTest extends TestCase
{
    private LoggerInterface&MockObject $logger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeModel(string $modelId): AiModel
    {
        $model = new AiModel();
        $model->model_id = $modelId;
        return $model;
    }

    private function makeCollection(AiModel ...$models): AiModelCollection
    {
        return new AiModelCollection($models);
    }

    private function makeSut(AiModel ...$models): AiModelDemandCollection
    {
        return new AiModelDemandCollection($this->makeCollection(...$models), $this->logger);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(AiModelDemandCollection::class, $sut);
    }

    public function testItInitialisesAllModelsToLowDemand(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::LOW, $list['gpt-4']);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsSuppliedCollection(): void
    {
        $model = $this->makeModel('gpt-4');
        $collection = $this->makeCollection($model);
        $sut = new AiModelDemandCollection($collection, $this->logger);

        static::assertSame($collection, $sut->getModels());
    }

    // =========================================================================
    // set
    // =========================================================================

    public function testItSetUpdatesDemandByModelInstance(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->set($model, ModelDemand::HIGH);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::HIGH, $list['gpt-4']);
    }

    public function testItSetUpdatesDemandByStringId(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->set('gpt-4', ModelDemand::MEDIUM);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::MEDIUM, $list['gpt-4']);
    }

    public function testItSetIgnoresModelNotInCollection(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        // Should not throw; unknown model is silently skipped
        $sut->set('unknown-model', ModelDemand::HIGH);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::LOW, $list['gpt-4']);
        static::assertArrayNotHasKey('unknown-model', $list);
    }

    public function testItSetLogsDebugWhenModelNotInCollection(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $this->logger->expects(static::once())->method('debug');

        $sut->set('unknown-model', ModelDemand::HIGH);
    }

    public function testItSetLogsInfoWhenDemandIsUpdated(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $this->logger->expects(static::once())->method('info');

        $sut->set($model, ModelDemand::HIGH);
    }

    // =========================================================================
    // setLow / setMedium / setHigh
    // =========================================================================

    public function testItSetLowStoresLowDemand(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->set($model, ModelDemand::HIGH);
        $sut->setLow($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::LOW, $list['gpt-4']);
    }

    public function testItSetMediumStoresMediumDemand(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->setMedium($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::MEDIUM, $list['gpt-4']);
    }

    public function testItSetHighStoresHighDemand(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->setHigh($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::HIGH, $list['gpt-4']);
    }

    // =========================================================================
    // getChangedList
    // =========================================================================

    public function testItGetChangedListContainsAllModels(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $list = iterator_to_array($sut->getChangedList());
        static::assertArrayHasKey('gpt-4', $list);
        static::assertArrayHasKey('claude-3', $list);
    }

    public function testItGetChangedListReflectsLatestDemandValues(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $sut->setHigh($m1);
        $sut->setMedium($m2);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(ModelDemand::HIGH, $list['gpt-4']);
        static::assertSame(ModelDemand::MEDIUM, $list['claude-3']);
    }

    // =========================================================================
    // getIterator
    // =========================================================================

    public function testItIsIterableAndYieldsModels(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $iterated = [];
        foreach ($sut as $m) {
            $iterated[] = $m;
        }

        static::assertCount(1, $iterated);
        static::assertSame($model, $iterated[0]);
    }

    public function testItIteratesAllModels(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $iterated = iterator_to_array($sut);
        static::assertCount(2, $iterated);
    }
}
