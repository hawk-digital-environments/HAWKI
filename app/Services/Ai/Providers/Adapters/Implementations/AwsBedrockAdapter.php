<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Bedrock\Concerns\CreatesBedrockClient;
use Laravel\Ai\Providers\Provider as Driver;

class AwsBedrockAdapter extends AbstractProviderAdapter
{
    use CreatesBedrockClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        return $factory->make(
            driverName: Lab::Bedrock,

        );
    }

    public function createHttpClient(AiProviderProxy $provider): PendingRequest
    {
        $this->createBedrockClient()->list()
        // Since AWS uses its own SDK for Bedrock,
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        // Again, I did not find an API endpoint to list AWS Bedrock models, if you find one, please open a PR to implement it.
        return collect();
    }

    public function createNeuronProvider(AgentRequestContext $source): AIProviderInterface
    {
        $key = $source->provider->api_key;
        $keyParts = explode(' ', $key, 2);
        if (!is_array($keyParts) || count($keyParts) !== 2) {
            throw InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat($key);
        }

        $adapterSettings = $source->provider->settings->getAdapterSettings();
        $client = new BedrockRuntimeClient([
            'version' => $adapterSettings['version'] ?? 'latest',
            'region' => $adapterSettings['region'] ?? 'eu-central-1',
            'credentials' => [
                'key' => $keyParts[0],
                'secret' => $keyParts[1],
            ],
        ]);

        return new BedrockRuntime(
            bedrockRuntimeClient: $client,
            model: $source->model->model_id,
            /* @see https://docs.aws.amazon.com/bedrock/latest/APIReference/API_runtime_InferenceConfiguration.html */
            inferenceConfig: array_merge(
                [
                    'maxTokens' => $source->getMaxTokens(),
                    'temperature' => $source->getTemperature(),
                    'topP' => $source->getTopP(),
                ],
                $source->toAdditionalArray()
            )
        );
    }

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, AgentRequestContext $source): void
    {
        // I did not find an API endpoint to check AWS Bedrock model status, if you find one, please open a PR to implement it. For now, we'll just assume all models are online.
        $statusCollection->setAllOnline();
    }

}
