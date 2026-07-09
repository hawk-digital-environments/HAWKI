<?php
declare(strict_types=1);

namespace Tests\Unit\Services\AI\Providers\Adapters\Exceptions;

use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

#[CoversClass(InvalidProviderConfigurationException::class)]
class InvalidProviderConfigurationExceptionTest extends TestCase
{
    // =========================================================================
    // forAwsBedrockApiKeyFormat
    // =========================================================================

    public function testItThrowsForAwsBedrockApiKeyFormatWithStaticCredentials(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('AKIAIOSFODNN7EXAMPLE wJalrXUtn');
        static::assertInstanceOf(InvalidProviderConfigurationException::class, $sut);
        static::assertStringContainsString('Invalid API key format for AWS Bedrock provider', $sut->getMessage());
    }

    public function testItIsAnInvalidArgumentException(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('bad-key');
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItRedactsLongKeyPartsLeavingLastThreeChars(): void
    {
        // A long key: "ABCDEFGHIJ" → redacted to "???????HIJ"
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('ABCDEFGHIJ secretXYZ');
        $message = $sut->getMessage();
        static::assertStringContainsString('HIJ', $message);
        static::assertStringContainsString('XYZ', $message);
        // The full key must not appear in the message
        static::assertStringNotContainsString('ABCDEFG', $message);
    }

    public function testItRedactsShortKeyPartWithFullAsterisks(): void
    {
        // A 3-char part "abc" → fully redacted to "***"
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('abc XYZ');
        static::assertStringContainsString('***', $sut->getMessage());
    }

    public function testItPreservesTokenPrefixInRedactedMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('token:ABCDEFGHIJ');
        static::assertStringContainsString('token:', $sut->getMessage());
    }

    public function testItRedactsTokenValueLeavingLastThreeChars(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('token:ABCDEFGHIJ');
        $message = $sut->getMessage();
        static::assertStringContainsString('HIJ', $message);
        static::assertStringNotContainsString('ABCDEFG', $message);
    }

    public function testItContainsExpectedFormatsInMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('bad');
        static::assertStringContainsString('AWS_BEDROCK_KEY AWS_BEDROCK_SECRET', $sut->getMessage());
        static::assertStringContainsString('token:AWS_BEARER_TOKEN', $sut->getMessage());
    }

    // =========================================================================
    // forMissingApiUrl
    // =========================================================================

    public function testItCreatesExceptionForMissingApiUrl(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'my_adapter');
        static::assertInstanceOf(InvalidProviderConfigurationException::class, $sut);
    }

    public function testItIncludesProviderNameInMissingApiUrlMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'my_adapter');
        static::assertStringContainsString('MyProvider', $sut->getMessage());
    }

    public function testItIncludesAdapterKeyInMissingApiUrlMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'my_adapter');
        static::assertStringContainsString('my_adapter', $sut->getMessage());
    }

    #[DataProvider('provideTestItForMissingApiUrlFormatData')]
    public function testItForMissingApiUrlFormat(string $providerName, string $adapterKey): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl($providerName, $adapterKey);
        static::assertStringContainsString(
            sprintf(
                'API URL is required for provider "%s" with adapter key "%s".',
                $providerName,
                $adapterKey
            ),
            $sut->getMessage()
        );
    }

    public static function provideTestItForMissingApiUrlFormatData(): iterable
    {
        yield 'openai-like provider' => ['OpenAI Compatible', 'openai_like'];
        yield 'ollama provider' => ['Ollama Local', 'ollama'];
        yield 'azure provider' => ['Azure OpenAI', 'openai_azure'];
    }
}
