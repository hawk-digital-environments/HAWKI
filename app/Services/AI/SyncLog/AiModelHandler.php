<?php
declare(strict_types=1);


namespace App\Services\AI\SyncLog;


use App\Events\AiModelNumericIdAssignedEvent;
use App\Events\AiModelStatusUpdateEvent;
use App\Http\Resources\AiModelResource;
use App\Services\AI\AiService;
use App\Services\AI\Db\ModelIdMapDb;
use App\Services\AI\Value\AiModel;
use App\Services\ExtApp\ExtAppFeatureSwitch;
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
        private readonly AiService           $aiService,
        private readonly ModelIdMapDb        $modelIdMapDb,
        private readonly ExtAppFeatureSwitch $featureSwitch
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
        return new AiModelResource($model);
    }

    /**
     * @inheritDoc
     */
    public function findModelById(int $id): ?AiModel
    {
        $modelId = $this->modelIdMapDb->getModelIdByNumeric($id);

        if ($modelId === null) {
            return null;
        }

        return $this->aiService->getModel($modelId);
    }

    /**
     * @inheritDoc
     */
    public function getIdOfModel(mixed $model): int
    {
        return $this->modelIdMapDb->getOrAssignNumericId($model);
    }

    /**
     * @inheritDoc
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int
    {
        return $this->aiService->getAvailableModels()->models->count();
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return $this->aiService->getAvailableModels()->models->toCollection();
    }
}
