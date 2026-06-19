<?php
declare(strict_types=1);


namespace App\Services\Ai\ProviderAdapters\Implementations;


use App\Services\Ai\ProviderAdapters\AbstractProviderAdapter;
use App\Services\Ai\StatusCheck\AiModelDemandCollection;
use App\Services\Ai\StatusCheck\AiModelOnlineStatusCollection;
use App\Services\Ai\Values\ParameterSource;
use Aws\BedrockRuntime\BedrockRuntimeClient;
use NeuronAI\Providers\AIProviderInterface;
use NeuronAI\Providers\AWS\BedrockRuntime;

class AwsBedrockAdapter extends AbstractProviderAdapter
{
    public function createNeuronProvider(ParameterSource $source): AIProviderInterface
    {
        $key = $source->getProvider()->api_key;
        $keyParts = explode(' ', $key, 2);
        if (!is_array($keyParts) || count($keyParts) !== 2) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid API key format for AWS Bedrock provider. Expected format: "AWS_BEDROCK_KEY AWS_BEDROCK_SECRET". Got: "%s"',
                $key
            ));
        }

        $adapterSettings = $source->getProvider()->settings->getAdapterSettings();
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
            model: $source->getModel()->model_id,
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

    public function checkModelStatus(AiModelOnlineStatusCollection $statusCollection, AiModelDemandCollection $demandCollection, ParameterSource $source): void
    {
        // I did not find an API endpoint to check AWS Bedrock model status, if you find one, please open a PR to implement it. For now, we'll just assume all models are online.
        $statusCollection->setAllOnline();
    }

}
