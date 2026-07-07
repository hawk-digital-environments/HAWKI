<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Adapters\Implementations;


use App\Models\Ai\AiProvider;
use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use App\Services\Ai\Providers\Adapters\AbstractProviderAdapter;
use App\Services\Ai\Providers\Adapters\DriverFactory;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use Aws\Bedrock\BedrockClient;
use Illuminate\Support\Collection;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Gateway\Bedrock\Concerns\CreatesBedrockClient;
use Laravel\Ai\Providers\Provider as Driver;

class AwsBedrockAdapter extends AbstractProviderAdapter
{
    use CreatesBedrockClient;

    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        $providerKey = $provider->api_key;

        $token = null;
        $secret = null;
        $key = null;
        if (!str_contains($key, ' ') && str_starts_with($key, 'token:')) {
            $token = substr($providerKey, strlen('token:'));
        } else if (str_contains($providerKey, ' ') && substr_count($providerKey, ' ') === 1) {
            [$key, $secret] = explode(' ', $providerKey, 2);
        } else {
            throw InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat($providerKey);
        }

        $adapterSettings = $provider->settings->getAdapterSettings();
        return $factory->make(
            driverName: Lab::Bedrock,
            config: [
                'version' => $adapterSettings['version'] ?? 'latest',
                'region' => $adapterSettings['region'] ?? 'eu-central-1',
                'access_key_id' => $key,
                'secret_access_key' => $secret,
                'key' => $token
            ]
        );
    }

    private function createBedrockClient(AiProviderProxy $provider, ?int $timeout = null): BedrockClient
    {
        $credentials = $provider->driver->providerCredentials();

        $config = $provider->driver->additionalConfiguration();

        $clientConfig = [
            'region' => $config['region'] ?? 'us-east-1',
            'version' => '2023-09-30',
            ...$this->resolveAuthConfig($credentials, $config),
        ];

        if ($timeout) {
            $clientConfig['http'] = ['timeout' => $timeout];
        }

        return new BedrockClient($clientConfig);
    }

    /**
     * @inheritDoc
     */
    public function getModels(AiProviderProxy $provider): Collection
    {
        $collection = collect();
        foreach ($this->createBedrockClient($provider, 10)->listFoundationModels()['modelSummaries'] as $model) {
            $collection->push($this->createNewChatModelInfo(
                modelId: $model['modelId'],
                provider: $provider,
            ));
        }
        return $collection;
    }
}
