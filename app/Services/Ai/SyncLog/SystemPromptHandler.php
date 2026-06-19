<?php
declare(strict_types=1);


namespace App\Services\Ai\SyncLog;


use App\Http\Resources\SystemPromptResource;
use App\Models\Ai\SystemPrompt;
use App\Services\Ai\Repositories\SystemPromptRepository;
use App\Services\Ai\Values\SystemPromptType;
use App\Services\SyncLog\Handlers\AbstractStaticSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use App\Services\System\Database\Eloquent\Repositories\Value\ScopeOverrides;
use App\Services\Translation\LocaleService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractStaticSyncLogHandler<SystemPrompt>
 */
class SystemPromptHandler extends AbstractStaticSyncLogHandler
{
    public function __construct(
        private readonly LocaleService          $localeService,
        private readonly SystemPromptRepository $repository
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function getType(): SyncLogEntryType
    {
        return SyncLogEntryType::SYSTEM_PROMPT;
    }

    /**
     * @inheritDoc
     */
    public function convertModelToResource(mixed $model): JsonResource
    {
        return new SystemPromptResource($model);
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
        return count(SystemPromptType::cases()) * count($this->localeService->getAvailableLocales());
    }

    /**
     * @inheritDoc
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection
    {
        return collect($this->repository->findAll(
        // Disabling the "locale" scope, means that we will get all system prompts regardless of the locale.
            scopeOverrides: ScopeOverrides::makeWithForcefullyDisabled('locale')
        ));
    }
}
