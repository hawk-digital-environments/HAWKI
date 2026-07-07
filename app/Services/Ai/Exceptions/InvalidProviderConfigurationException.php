<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidProviderConfigurationException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forAwsBedrockApiKeyFormat(string $key): self
    {
        // Replace everything except the leading "token:", and the last 3 chars of each word with asterisks for redaction
        $keyRedacted = (static function (string $key): string {
            $keyStartsWithToken = false;
            if (str_starts_with(strtolower(trim($key)), 'token:')) {
                $keyStartsWithToken = true;
                $key = substr($key, 6); // Remove "token:" prefix for processing
            }

            $keyRedacted = implode(' ', array_map(static function ($part) {
                $partLength = strlen($part);
                if ($partLength <= 3) {
                    return str_repeat('*', $partLength);
                }
                return str_repeat('*', $partLength - 3) . substr($part, -3);
            }, explode(' ', $key)));

            return $keyStartsWithToken ? 'token:' . $keyRedacted : $keyRedacted;
        })($key);

        return new self(sprintf(
            'Invalid API key format for AWS Bedrock provider. Expected format: "AWS_BEDROCK_KEY AWS_BEDROCK_SECRET", or "token:AWS_BEARER_TOKEN". Got: "%s".',
            $keyRedacted
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
