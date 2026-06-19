<?php
declare(strict_types=1);


namespace App\Services\Ai\Agent\Chat\Values;


use App\Models\Ai\AiModel;
use App\Services\Ai\Agent\Contracts\AgentRequestInterface;
use App\Services\Ai\Values\ModelParameters;
use App\Services\Ai\Values\ParameterSource;

readonly class ChatRequest implements AgentRequestInterface
{
    public function __construct(
        public AiModel         $model,
        public ModelParameters $parameters,
        public string          $usageType,
        public string          $instructions,
        public array           $capabilities,
        public array           $tools,
        public array           $messages,
        public bool            $streaming
    )
    {

    }

    public function getParameterSource(): ParameterSource
    {
        return ParameterSource::fromModel(
            model: $this->model,
            parameters: $this->parameters,
            usageType: $this->usageType
        );
    }
}
