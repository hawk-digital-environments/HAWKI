<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\StatusCheck;

use App\Collections\AiModelCollection;
use App\Models\Ai\AiModel;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\OnlineStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

#[CoversClass(AiModelOnlineStatusCollection::class)]
class AiModelOnlineStatusCollectionTest extends TestCase
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

    private function makeSut(AiModel ...$models): AiModelOnlineStatusCollection
    {
        return new AiModelOnlineStatusCollection($this->makeCollection(...$models), $this->logger);
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = $this->makeSut();
        static::assertInstanceOf(AiModelOnlineStatusCollection::class, $sut);
    }

    public function testItInitialisesAllModelsToUnknown(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::UNKNOWN, $list['gpt-4']);
    }

    // =========================================================================
    // getModels
    // =========================================================================

    public function testItGetModelsReturnsSuppliedCollection(): void
    {
        $model = $this->makeModel('gpt-4');
        $collection = $this->makeCollection($model);
        $sut = new AiModelOnlineStatusCollection($collection, $this->logger);

        static::assertSame($collection, $sut->getModels());
    }

    // =========================================================================
    // set
    // =========================================================================

    public function testItSetUpdatesStatusByModelInstance(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->set($model, OnlineStatus::ONLINE);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
    }

    public function testItSetUpdatesStatusByStringId(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->set('gpt-4', OnlineStatus::OFFLINE);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::OFFLINE, $list['gpt-4']);
    }

    public function testItSetIgnoresModelNotInCollection(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $sut->set('unknown-model', OnlineStatus::ONLINE);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::UNKNOWN, $list['gpt-4']);
        static::assertArrayNotHasKey('unknown-model', $list);
    }

    public function testItSetLogsDebugWhenModelNotInCollection(): void
    {
        $sut = $this->makeSut($this->makeModel('gpt-4'));

        $this->logger->expects(static::once())->method('debug');

        $sut->set('unknown-model', OnlineStatus::ONLINE);
    }

    public function testItSetLogsInfoWhenStatusIsUpdated(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);

        $this->logger->expects(static::once())->method('info');

        $sut->set($model, OnlineStatus::ONLINE);
    }

    // =========================================================================
    // setOnline / setOffline / setUnknown
    // =========================================================================

    public function testItSetOnlineStoresOnlineStatus(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->setOnline($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
    }

    public function testItSetOfflineStoresOfflineStatus(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->setOffline($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::OFFLINE, $list['gpt-4']);
    }

    public function testItSetUnknownResetsStatusToUnknown(): void
    {
        $model = $this->makeModel('gpt-4');
        $sut = $this->makeSut($model);
        $sut->setOnline($model);
        $sut->setUnknown($model);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::UNKNOWN, $list['gpt-4']);
    }

    // =========================================================================
    // setAllOnline / setAllOffline / setAllUnknown
    // =========================================================================

    public function testItSetAllOnlineMarksEveryModelOnline(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);
        $sut->setAllOnline();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::ONLINE, $list['claude-3']);
    }

    public function testItSetAllOfflineMarksEveryModelOffline(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);
        $sut->setAllOffline();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::OFFLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::OFFLINE, $list['claude-3']);
    }

    public function testItSetAllUnknownResetsEveryModelToUnknown(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);
        $sut->setAllOnline();
        $sut->setAllUnknown();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::UNKNOWN, $list['gpt-4']);
        static::assertSame(OnlineStatus::UNKNOWN, $list['claude-3']);
    }

    // =========================================================================
    // setAllUnknownToOffline
    // =========================================================================

    public function testItSetAllUnknownToOfflinePromotesUnknownModels(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $sut->setAllUnknownToOffline();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::OFFLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::OFFLINE, $list['claude-3']);
    }

    public function testItSetAllUnknownToOfflineDoesNotAffectAlreadyResolvedModels(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $sut->setOnline($m1);
        $sut->setOffline($m2);
        $sut->setAllUnknownToOffline();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::OFFLINE, $list['claude-3']);
    }

    public function testItSetAllUnknownToOfflineOnlyPromotesUnknownOnes(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $sut->setOnline($m1);
        // m2 stays UNKNOWN
        $sut->setAllUnknownToOffline();

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::OFFLINE, $list['claude-3']);
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

    public function testItGetChangedListReflectsLatestStatusValues(): void
    {
        $m1 = $this->makeModel('gpt-4');
        $m2 = $this->makeModel('claude-3');
        $sut = $this->makeSut($m1, $m2);

        $sut->setOnline($m1);
        $sut->setOffline($m2);

        $list = iterator_to_array($sut->getChangedList());
        static::assertSame(OnlineStatus::ONLINE, $list['gpt-4']);
        static::assertSame(OnlineStatus::OFFLINE, $list['claude-3']);
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
