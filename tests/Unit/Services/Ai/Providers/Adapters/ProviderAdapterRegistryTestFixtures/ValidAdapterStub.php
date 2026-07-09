<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Providers\Adapters\ProviderAdapterRegistryTestFixtures;

use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Support\Collection;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Providers\Provider as Driver;

/**
 * Minimal ProviderAdapterInterface implementation used as a test fixture.
 */
class ValidAdapterStub implements ProviderAdapterInterface
{
    public function getNameLabel(): string|null
    {
        return null;
    }

    public function getDescriptionLabel(): string|null
    {
        return null;
    }

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        throw new \LogicException('Not implemented in stub.');
    }

    public function getAdditionalDriverOptions(Agent $agent, AgentRequestContext $context): array
    {
        return [];
    }

    public function supportsFileAsAttachment(FileInterface $file): bool
    {
        return true;
    }

    public function getModels(AiProviderProxy $provider): Collection
    {
        return new Collection();
    }

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void {
    }

    public function getNativeToolFactoryForCapability(string $capability): \Closure|null
    {
        return null;
    }
}
