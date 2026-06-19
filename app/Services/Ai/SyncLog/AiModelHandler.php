<?php
declare(strict_types=1);


namespace App\Services\Ai\SyncLog;


use App\Events\AiModelNumericIdAssignedEvent;
use App\Events\AiModelStatusUpdateEvent;
use App\Models\Ai\AiModel;
use App\Services\Ai\Repositories\AiModelRepository;
use App\Services\ExtApp\Values\ExtAppFeatureSwitch;
use App\Services\SyncLog\Handlers\AbstractSyncLogHandler;
use App\Services\SyncLog\Handlers\Contract\ConditionalSyncLogHandlerInterface;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractSyncLogHandler<AiModel>
 */
class AiModelHandler extends AbstractSyncLogHandler implements ConditionalSyncLogHandlerInterface
{
    public function __construct(
        private readonly ExtAppFeatureSwitch $featureSwitch,
        private readonly AiModelRepository   $repository
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::AI_MODEL;
    }

    /**
     * @inheritDoc
     */
    public function canTrack(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function canProvide(): bool
    {
        return !$this->featureSwitch->isExtAppRequest() || $this->featureSwitch->isAiInGroupsEnabled();
    }

    /**
     * @inheritDoc
     */
    public function listeners(): array
    {
        $handleSet = function (AiModelStatusUpdateEvent|AiModelNumericIdAssignedEvent $event) {
            return $this->createSetPayload(
                $event->model,
                null
            );
        };
        return [
            AiModelStatusUpdateEvent::class => $handleSet,
            AiModelNumericIdAssignedEvent::class => $handleSet,
        ];
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
//        return new AiModelResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?AiModel
    {
        return $this->repository->findOne($id);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        return $model->id;
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
//        return $this->repository->countAllActiveWithFallback();
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
//        return $this->repository->findAllActiveWithFallback();
    }
}
