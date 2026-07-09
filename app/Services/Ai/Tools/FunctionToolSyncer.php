<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools;


use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\Events\FunctionToolSyncCompletedEvent;
use App\Services\Ai\Tools\Events\FunctionToolSyncedEvent;
use App\Services\Ai\Tools\Events\FunctionToolSyncFailedEvent;
use App\Services\Ai\Tools\Events\FunctionToolSyncStartingEvent;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Tag;
use Psr\Log\LoggerInterface;

/**
 * Upserts all PHP function-calling tools registered in the service container into the database.
 *
 * The set of tools is collected via the `#[Tag(ToolInterface::class)]` DI tag — every class
 * bound with that tag is processed. This runs at deployment time (via `ai:tools:sync`) so that
 * the database always reflects the currently registered tool classes.
 *
 * Each tool is passed to {@see AiToolRepository::upsertFunction()}, which creates or updates
 * the corresponding `ai_tools` row. Tools that are no longer registered are *not* automatically
 * removed; removal is a deliberate manual step to avoid accidental data loss.
 *
 * Events emitted during a sync:
 *  - {@see FunctionToolSyncStartingEvent} — before iteration begins (carries the metrics object).
 *  - {@see FunctionToolSyncedEvent}        — after each successful upsert.
 *  - {@see FunctionToolSyncFailedEvent}    — when a single tool's upsert throws.
 *  - {@see FunctionToolSyncCompletedEvent} — after all tools have been processed.
 */
readonly class FunctionToolSyncer
{
    public function __construct(
        /**
         * @var iterable<ToolInterface> $functionTools
         */
        #[Tag(ToolInterface::class)]
        private iterable         $functionTools,
        private AiToolRepository $toolRepository,
        private LoggerInterface  $logger
    )
    {
    }

    /**
     * Iterates all tagged {@see ToolInterface} instances and upserts each into the database.
     *
     * Failures for individual tools are caught and recorded in the returned metrics without
     * aborting the rest of the sync, so a single broken tool cannot block others.
     */
    public function sync(): JobMetrics
    {
        $metrics = new JobMetrics('Function Tool Sync', $this->logger);

        $metrics->announceStart();

        FunctionToolSyncStartingEvent::dispatch($metrics);

        foreach ($this->functionTools as $tool) {
            try {
                $synced = $this->toolRepository->upsertFunction($tool, true);

                FunctionToolSyncedEvent::dispatch($tool, $metrics, $synced);

                $metrics->increment('Function Tools synced');
            } catch (\Throwable $e) {
                $metrics->error(sprintf(
                    'Failed to sync function tool %s: %s',
                    get_class($tool),
                    $e->getMessage()
                ), ['exception' => $e]);

                FunctionToolSyncFailedEvent::dispatch($metrics, $tool, $e);
            }
        }

        FunctionToolSyncCompletedEvent::dispatch($metrics);

        $metrics->announceCompletion();

        return $metrics;
    }

}
