<?php
declare(strict_types=1);


namespace App\Services\AI\SyncLog;


use App\Http\Resources\SystemPromptResource;
use App\Services\AI\SystemPromptProvider;
use App\Services\AI\Utils\SystemPromptIdGenerator;
use App\Services\AI\Value\SystemPrompt;
use App\Services\AI\Value\SystemPromptType;
use App\Services\SyncLog\Handlers\AbstractStaticSyncLogHandler;
use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryType;
use App\Services\Translation\LocaleService;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @extends AbstractStaticSyncLogHandler<SystemPrompt>
 */
class SystemPromptHandler extends AbstractStaticSyncLogHandler
{
    public function __construct(
        private readonly LocaleService           $localeService,
        private readonly SystemPromptProvider    $promptProvider,
        private readonly SystemPromptIdGenerator $idGenerator
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
        return $this->idGenerator->getIdFor($model);
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
        $prompts = [];
        
        foreach ($this->localeService->getAvailableLocales() as $locale) {
            $prompts[] = $this->promptProvider->getAllPrompts($locale);
        }
        
        return collect(array_merge(...$prompts));
    }
}
