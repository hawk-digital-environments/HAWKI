<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Adapters;


use App\Services\Ai\Agents\Contracts\AgentInterface as HawkiAgentInterface;
use App\Services\Ai\Agents\Exceptions\AgentStateException;
use App\Services\Ai\LaravelAi\Values\ProviderDriverPortal;
use App\Services\Ai\Values\TokenUsage;
use Laravel\Ai\Contracts\Agent as LaravelAgentInterface;
use Laravel\Ai\Promptable;
use Laravel\Ai\Responses\AgentResponse;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;

abstract class AbstractLaravelAgent implements LaravelAgentInterface, HawkiAgentInterface
{
    use Promptable;

    private Usage|null $usage = null;

    abstract protected function getPromptString(): string;

    protected function getAttachments(): array
    {
        return [];
    }

    public function getUsage(): TokenUsage
    {
        if (!$this->usage) {
            throw AgentStateException::forUsageNotAvailable();
        }

        return TokenUsage::fromLaravelUsage($this->usage, $this->getContext()->model);
    }

    public function send(): AgentResponse
    {
        // @todo event $this $context $provider

        $response = $this->prompt(
            prompt: $this->getPromptString(),
            attachments: $this->getAttachments(),
            provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
            model: $this->getContext()->model->model_id
        );

        $this->usage = $response->usage;

        // @todo event $this $context $provider $response $usage

        return $response;
    }

    public function sendStreaming(): StreamableAgentResponse
    {
        // @todo event $this $context $provider

        $response = $this->stream(
            prompt: $this->getPromptString(),
            attachments: $this->getAttachments(),
            provider: (string)ProviderDriverPortal::fromProviderProxy($this->getContext()->provider),
            model: $this->getContext()->model->model_id
        );

        $response->then(function (AgentResponse $response) {
            $this->usage = $response->usage;

            // @todo event $this $context $provider $response $usage
        });

        // @todo event $this $context $provider $response

        return $response;
    }
}
