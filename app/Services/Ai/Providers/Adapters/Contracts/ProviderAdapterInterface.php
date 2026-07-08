<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Contracts;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;
use Laravel\Ai\Providers\Tools\ProviderTool;

interface ProviderAdapterInterface
{
    public function getNameLabel(): string|null;

    public function getDescriptionLabel(): string|null;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver;

    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array;

    public function supportsFileAsAttachment(FileInterface $file): bool;

    /**
     * @param AiProviderProxy $provider
     * @return Collection<AiModel>
     */
    public function getModels(AiProviderProxy $provider): Collection;

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void;

    /**
     * @param string $capability
     * @return (\Closure(AgentRequestContext $context, array $toolSettings):ProviderTool)|null
     */
    public function getNativeToolFactoryForCapability(string $capability): \Closure|null;
}
