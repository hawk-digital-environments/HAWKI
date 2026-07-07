<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\OpenAiCompatibleProvider;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterCreatesDriverInterface;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\StatusCheck\Values\ModelDemand;
use App\Services\Ai\Values\OnlineStatus;
use App\Services\Storage\Interfaces\FileInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Laravel\Ai\Providers\Provider as Driver;

class GwdgAdapter extends OpenAiLikeAdapter implements ProviderAdapterCreatesDriverInterface
{
    public const string DEFAULT_DOCUMENTATION_URL = 'https://docs.hpc.gwdg.de/services/ai-services/chat-ai/models/index.html';

    public function supportsFileAsAttachment(FileInterface $file): bool
    {
        return str_starts_with($file->getMimeType(), 'image/');
    }

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        /* IMPORTANT
         * Currently, Laravel AI does not support chat completion endpoints (only responses)!
         * There is already an open PR to add support for chat completion endpoints, but it is not yet merged.
         * For more details, see: {@see app/Services/Ai/LaravelAi/Drivers/OpenaiCompatible/README.md}
        */
        return $factory->make(
            driverName: 'openai-compatible',
            config: [
                'key' => $provider->api_key,
                'url' => $provider->api_url ?? 'https://chat-ai.academiccloud.de/v1',
            ],
            builder: function (Dispatcher $dispatcher, array $config) {
                return new OpenAiCompatibleProvider(
                    $config,
                    $dispatcher
                );
            }
        );
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        return $this->fetchOpenAiModelList(
            $provider,
            $this->createModelListClient($provider),
            alternativeMapper: function (array $item) use ($provider) {
                $input = data_get($item, 'input', []);
                $output = data_get($item, 'output', []);
                $inputOutputContainsText = in_array('text', $input) && in_array('text', $output);
                $modelInfo = $this->createNewModelInfo(
                    modelId: data_get($item, 'id'),
                    provider: $provider,
                    modelType: $inputOutputContainsText ? WellKnownModelTypes::CHAT : null
                );
                $modelInfo->label = data_get($item, 'name');
                $modelInfo->documentation_url = self::DEFAULT_DOCUMENTATION_URL;
                $this->enrichInput($modelInfo, $input);
                $this->enrichOutput($modelInfo, $output);
                return $modelInfo;
            }
        );
    }

    public function checkModelStatus(
        AiModelOnlineStatusCollection $statusCollection,
        AiModelDemandCollection       $demandCollection,
        AiProviderProxy               $provider
    ): void
    {
        $this->runOpenAiStatusCheck(
            $statusCollection,
            $this->createModelListClient($provider),
            alternativeMapper: function (string $modelId, array $data) use ($statusCollection, $demandCollection) {
                $status = match ($data['status']) {
                    'ready' => OnlineStatus::ONLINE,
                    'offline' => OnlineStatus::OFFLINE,
                    default => OnlineStatus::UNKNOWN,
                };
                $statusCollection->set($modelId, $status);

                $demandInt = data_get($data, 'demand', 0);
                $demand = match (true) {
                    $demandInt >= 4 => ModelDemand::HIGH,
                    $demandInt >= 2 => ModelDemand::MEDIUM,
                    default => ModelDemand::LOW,
                };
                $demandCollection->set($modelId, $demand);
            }
        );
    }
}
