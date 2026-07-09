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

/**
 * Provider adapter for AWS Bedrock.
 *
 * Supports two authentication modes, selected by the format of the stored API key:
 *   - Static IAM credentials: `"AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY"` (space-separated)
 *   - Bearer token:           `"token:AWS_BEARER_TOKEN"`
 *
 * Region and API version can be overridden via the provider's adapter settings; the
 * defaults are `eu-central-1` for the driver and `us-east-1` for direct SDK calls.
 *
 * The model list is fetched using the AWS SDK's `listFoundationModels` call rather than
 * a REST endpoint, because Bedrock does not expose an OpenAI-compatible `/models` route.
 * All returned models are treated as chat-capable.
 */
class AwsBedrockAdapter extends AbstractProviderAdapter
{
    use CreatesBedrockClient;

    /**
     * Creates a Bedrock driver instance from the provider's API key.
     *
     * Parses `$provider->api_key` to determine the auth mode:
     *   - `"KEY SECRET"` — static IAM credentials passed as `access_key_id` / `secret_access_key`
     *   - `"token:TOKEN"` — bearer token passed as `key`
     *
     * Region and version fall back to `eu-central-1` / `latest` when absent from adapter settings.
     *
     * @throws \App\Services\Ai\Exceptions\InvalidProviderConfigurationException when the key
     *         does not match either expected format.
     */
    public function createDriver(AiProvider $provider, DriverFactory $factory): Driver
    {
        $providerKey = $provider->api_key;

        $token = null;
        $secret = null;
        $key = null;
        if (str_starts_with($providerKey, 'token:')) {
            if (!str_contains($providerKey, ' ')) {
                $token = substr($providerKey, strlen('token:'));
            } else {
                throw InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat($providerKey);
            }
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

    /**
     * Builds an AWS SDK BedrockClient from the already-resolved driver credentials.
     *
     * Uses `us-east-1` as the default region for direct SDK calls (distinct from the
     * `eu-central-1` default used when building the Laravel AI driver). An optional
     * timeout can be passed for requests where a tight deadline is required (e.g. model
     * list polling).
     */
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
     * Fetches available foundation models from AWS Bedrock via the AWS SDK.
     *
     * Uses a 10-second HTTP timeout to prevent the model-list poll from blocking
     * indefinitely when Bedrock is temporarily unreachable. Every returned model is
     * treated as chat-capable via {@see createNewChatModelInfo()}.
     *
     * @return Collection<int, \App\Models\Ai\AiModel>
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
