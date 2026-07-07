<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidProviderConfigurationException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forAwsBedrockApiKeyFormat(string $key): self
    {
        return new self(sprintf(
            'Invalid API key format for AWS Bedrock provider. Expected format: "AWS_BEDROCK_KEY AWS_BEDROCK_SECRET". Got: "%s".',
            $key
        ));
    }

    public static function forMissingApiUrl(string $providerName, string $adapterKey): self
    {
        return new self(sprintf(
            'API URL is required for provider "%s" with adapter key "%s". Please provide a valid API URL in the provider configuration.',
            $providerName,
            $adapterKey
        ));
    }
}
