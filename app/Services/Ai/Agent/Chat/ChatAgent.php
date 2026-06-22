<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat;


use App\Services\Ai\Agent\Chat\Observer\LoggingObserver;
use App\Services\Ai\Agent\Chat\Values\ChatRequest;
use App\Services\Ai\Agent\Chat\Values\ChatResponse;
use App\Services\Ai\Agent\Chat\Values\StreamingChatResponse;
use App\Services\Ai\Agent\Contracts\AgentInterface;
use App\Services\Ai\Agent\Contracts\AgentRequestInterface;
use App\Services\Ai\Agent\Contracts\AgentResponseInterface;
use App\Services\Ai\Registries\ProviderAdapterRegistry;
use App\Services\Ai\Tools\Neuron\NeuronToolProvider;
use Illuminate\Container\Attributes\Singleton;
use NeuronAI\Agent\Agent;

#[Singleton]
readonly class ChatAgent implements AgentInterface
{
    public function __construct(
        private ProviderAdapterRegistry $providerAdapterRegistry,
        private NeuronToolProvider      $toolProvider,
        private LoggingObserver         $loggingObserver
    )
    {
    }


    public function sendRequest(AgentRequestInterface $request): AgentResponseInterface
    {
        if (!$request instanceof ChatRequest) {
            throw new \InvalidArgumentException('Invalid request type provided to ChatAgent. Expected instance of ChatRequest.');
        }

        if ($request->streaming) {
            return $this->respondToStreamRequest($request);
        }

        return $this->respondToNonStreamRequest($request);
    }

    private function respondToStreamRequest(ChatRequest $request): StreamingChatResponse
    {
        return new StreamingChatResponse(
            request: $request,
            agentFactory: fn() => $this->createNeuronAgent($request)
        );
    }

    private function respondToNonStreamRequest(ChatRequest $request): ChatResponse
    {
        $response = $this->createNeuronAgent($request)->chat($request->messages)->getMessage();
        return new ChatResponse(content: $response->getContent());
    }

    private function createNeuronAgent(ChatRequest $request): Agent
    {
        $providerAdapter = $this->providerAdapterRegistry->getForModel($request->model);

        $resolvedTools = null;
        if ($request->model->settings->canUseTools()) {
            $resolvedTools = $this->toolProvider->getTools(
                parameterSource: $request->getParameterSource(),
                requestedCapabilities: $request->capabilities,
                requestedTools: $request->tools
            );
        }

        // @todo filter $providerAdapter, $resolvedTools, $request(read)

        /** @var Agent $agent */
        $agent = Agent::make()
            ->toolMaxRuns($request->model->settings->getMaxToolCallingRounds())
            ->setAiProvider($providerAdapter->createNeuronProvider($request->getParameterSource()))
            ->setInstructions($request->instructions)
            // @phpstan-ignore method.notFound
            ->observe($this->loggingObserver);

        if ($resolvedTools !== null) {
            $agent->addTool($resolvedTools);
        }

        // @todo filter $agent $request(read)

        return $agent;
    }
}
