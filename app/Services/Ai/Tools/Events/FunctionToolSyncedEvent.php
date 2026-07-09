<?php
declare(strict_types=1);

namespace App\Services\Ai\Tools\Events;

use App\Models\Ai\AiTool;
use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Utils\JobMetrics;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Dispatched after a single function tool has been successfully upserted into the database
 * during {@see \App\Services\Ai\Tools\FunctionToolSyncer::sync()}.
 *
 * Listeners can use this event to:
 * - Log or audit which function tools were synced in a given run.
 * - Trigger downstream actions that depend on a specific tool being registered.
 * - Associate the synced tool record with external configuration.
 */
readonly class FunctionToolSyncedEvent
{
    use Dispatchable;

    public function __construct(
        /** The tool implementation that was synced. */
        public ToolInterface $tool,
        /** Structured metrics collector for this sync run. */
        public JobMetrics    $metrics,
        /** The persisted database record that was created or updated. */
        public AiTool        $synced,
    )
    {
    }
}
