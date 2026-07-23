<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;

/**
 * Thrown when a provider's persisted configuration violates a contract required by its adapter.
 *
 * Each static factory method targets a specific misconfiguration, redacts sensitive data where
 * necessary, and produces a message that explains what was expected and what was received so that
 * an operator can correct the database record without reading the source code.
 */
class InvalidProviderConfigurationException extends \InvalidArgumentException implements AiExceptionInterface
{
    /**
     * Thrown when the stored API key for an AWS Bedrock provider does not follow either of the
     * two accepted formats:
     *   - Static credentials: `"AWS_ACCESS_KEY_ID AWS_SECRET_ACCESS_KEY"` (space-separated)
     *   - Bearer token:       `"token:AWS_BEARER_TOKEN"`
     *
     * The key is partially redacted in the exception message — only the last three characters of
     * each space-separated word are kept — so the message can be safely logged without leaking secrets.
     */
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
                if ($partLength <= 6) {
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

    /**
     * Thrown when a provider adapter requires an API URL but the database record has none set.
     *
     * Adapters that target self-hosted or regional endpoints (e.g. OpenAI-compatible proxies,
     * on-premise deployments) cannot fall back to a default URL, so configuration is mandatory.
     */
    public static function forMissingApiUrl(string $providerName, string $adapterKey): self
    {
        return new self(sprintf(
            'API URL is required for provider "%s" with adapter key "%s". Please provide a valid API URL in the provider configuration.',
            $providerName,
            $adapterKey
        ));
    }
}
