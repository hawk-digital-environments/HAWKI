<?php
declare(strict_types=1);


namespace App\Services\AI\Utils;


use App\Services\AI\Interfaces\ClientInterface;
use App\Services\AI\Interfaces\ModelProviderInterface;
use App\Services\AI\Value\AiErrorResponse;
use App\Services\AI\Value\AiModel;
use App\Services\AI\Value\AiRequest;
use App\Services\AI\Value\AiResponse;
use App\Services\AI\Value\ModelOnlineStatus;
use Psr\Log\LoggerInterface;

/**
 * A Client decorator that adds logging capabilities to an underlying Client. It intercepts requests and responses,
 * and logs any errors encountered during AI interactions using the provided LoggerInterface.
 */
readonly class LoggingClient implements ClientInterface
{
    public function __construct(
        private ClientInterface $concreteClient,
        private LoggerInterface $logger,
        private bool            $isDebug
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function buildStack(ClientStack $stack): ClientStack
    {
        return $stack->push($this, $this->concreteClient);
    }

    /**
     * @inheritDoc
     */
    public function setProvider(ModelProviderInterface $provider): void
    {
        $this->concreteClient->setProvider($provider);
    }

    /**
     * @inheritDoc
     */
    public function sendRequest(AiRequest $request): AiResponse
    {
        $response = $this->concreteClient->sendRequest($request);

        if ($response instanceof AiErrorResponse && $this->isDebug) {
            $response = $response->withShowStacktrace(true);
        }

        if ($response->error !== null || $response instanceof AiErrorResponse) {
            $this->logger->error('AI request resulted in error', $this->buildErrorContext($response));
        }

        return $response;
    }

    /**
     * @inheritDoc
     */
    public function sendStreamRequest(AiRequest $request, callable $onData): void
    {
        // NOTE: $request is removed from use clause
        $this->concreteClient->sendStreamRequest($request, function (AiResponse $response) use ($onData) {
            if ($response->error !== null || $response instanceof AiErrorResponse) {
                $this->logger->error('AI streaming request resulted in error', $this->buildErrorContext($response));
            }

            $onData($response);
        });
    }

    /**
     * Build a sanitized error context for logging without including sensitive prompt data.
     */
    private function buildErrorContext(AiResponse $response): array
    {
        return [
            // Intentionally avoid logging full request/response to prevent leaking prompts or attachments.
            'response_class' => get_class($response),
            'has_error' => $response->error !== null,
            'error' => $response->error,
            'stack_trace' => $response instanceof AiErrorResponse ? $response->getStackTrace() : null
        ];
    }

    /**
     * @inheritDoc
     */
    public function getStatus(AiModel $model): ModelOnlineStatus
    {
        return $this->concreteClient->getStatus($model);
    }

}
