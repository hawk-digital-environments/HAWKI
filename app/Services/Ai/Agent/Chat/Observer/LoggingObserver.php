<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat\Observer;


use NeuronAI\Observability\Events\AgentError;
use NeuronAI\Observability\ObserverInterface;
use Psr\Log\LoggerInterface;

readonly class LoggingObserver implements ObserverInterface
{
    public function __construct(
        private LoggerInterface $logger
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function onEvent(string $event, object $source, mixed $data = null, ?string $branchId = null): void
    {
        if ($data instanceof AgentError) {
            $this->logger->error(sprintf(
                'An error occurred in agent %s: %s',
                get_class($source),
                substr($data->exception->getMessage(), 0, 200)
            ), ['exception' => $data->exception, 'branchId' => $branchId]);
            return;
        }

        $this->logger->debug("Agent event: $event");
    }
}
