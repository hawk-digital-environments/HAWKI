<?php
declare(strict_types=1);


namespace App\Services\Ai\Tools;


use App\Services\Ai\Tools\Contracts\ToolInterface;
use App\Services\Ai\Tools\Repositories\AiToolRepository;
use App\Utils\JobMetrics;
use Illuminate\Container\Attributes\Tag;
use Psr\Log\LoggerInterface;

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

    public function sync(): JobMetrics
    {
        $metrics = new JobMetrics('Function Tool Sync', $this->logger);

        $metrics->announceStart();

        // @todo event $metrics

        foreach ($this->functionTools as $tool) {
            try {
                $synced = $this->toolRepository->upsertFunction($tool, true);

                // @todo event $tool $metrics $synced

                $metrics->increment('Function Tools synced');
            } catch (\Throwable $e) {
                $metrics->error(sprintf(
                    'Failed to sync function tool %s: %s',
                    get_class($tool),
                    $e->getMessage()
                ), ['exception' => $e]);

                // @todo event $metrics $tool $e
            }
        }

        // @todo event $metrics

        $metrics->announceCompletion();

        return $metrics;
    }

}
