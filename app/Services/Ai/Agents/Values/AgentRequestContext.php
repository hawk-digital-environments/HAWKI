<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Values;


use App\Models\Ai\AiModel;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;

/**
 * @api
 */
readonly class AgentRequestContext
{
    public function __construct(
        public AiProviderProxy   $provider,
        public AiModel           $model,
        public AiModelParameters $modelParameters,
        public string            $usageType = WellKnownUsageTypes::MAIN_APP,
    )
    {
    }

    public function getParameters(): AiModelParameters
    {
        return $this->modelParameters;
    }
}
