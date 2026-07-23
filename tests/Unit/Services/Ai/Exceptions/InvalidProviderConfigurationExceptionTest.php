<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Exceptions;

use App\Services\Ai\Exceptions\InvalidProviderConfigurationException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidProviderConfigurationException::class)]
class InvalidProviderConfigurationExceptionTest extends TestCase
{
    // =========================================================================
    // forAwsBedrockApiKeyFormat
    // =========================================================================

    public function testItForAwsBedrockApiKeyFormatReturnsCorrectExceptionType(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('KEY SECRET');
        static::assertInstanceOf(InvalidProviderConfigurationException::class, $sut);
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItForAwsBedrockApiKeyFormatRedactsShortWords(): void
    {
        // Words of 3 chars or fewer are fully replaced with asterisks
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('KEY1 SEC1');
        static::assertStringContainsString('***', $sut->getMessage());
        static::assertStringContainsString('***', $sut->getMessage());
        static::assertStringNotContainsString('KEY1', $sut->getMessage());
        static::assertStringNotContainsString('SEC1', $sut->getMessage());
    }

    public function testItForAwsBedrockApiKeyFormatKeepsLastThreeCharsOfLongerWords(): void
    {
        // "ABCDEF" → "***DEF"
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('ABCDEFGHIJKL MNOPQRSTUVWXYZ');
        static::assertStringContainsString('JKL', $sut->getMessage());
        static::assertStringContainsString('XYZ', $sut->getMessage());
        static::assertStringNotContainsString('ABCDEF', $sut->getMessage());
    }

    public function testItForAwsBedrockApiKeyFormatPreservesTokenPrefix(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('token:MYTOKEN');
        static::assertStringContainsString('token:', $sut->getMessage());
    }

    public function testItForAwsBedrockApiKeyFormatHandlesTokenPrefixCaseInsensitively(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('TOKEN:MYTOKEN');
        static::assertStringContainsString('token:', $sut->getMessage());
    }

    public function testItForAwsBedrockApiKeyFormatMessageContainsExpectedFormatHint(): void
    {
        $sut = InvalidProviderConfigurationException::forAwsBedrockApiKeyFormat('bad');
        static::assertStringContainsString('AWS_BEDROCK_KEY AWS_BEDROCK_SECRET', $sut->getMessage());
        static::assertStringContainsString('token:AWS_BEARER_TOKEN', $sut->getMessage());
    }

    // =========================================================================
    // forMissingApiUrl
    // =========================================================================

    public function testItForMissingApiUrlReturnsCorrectExceptionType(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'openai');
        static::assertInstanceOf(InvalidProviderConfigurationException::class, $sut);
    }

    public function testItForMissingApiUrlIncludesProviderNameInMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'openai');
        static::assertStringContainsString('MyProvider', $sut->getMessage());
    }

    public function testItForMissingApiUrlIncludesAdapterKeyInMessage(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('MyProvider', 'openai');
        static::assertStringContainsString('openai', $sut->getMessage());
    }

    public function testItForMissingApiUrlMessageMatchesExpectedFormat(): void
    {
        $sut = InvalidProviderConfigurationException::forMissingApiUrl('Acme', 'acme-adapter');
        static::assertSame(
            'API URL is required for provider "Acme" with adapter key "acme-adapter". Please provide a valid API URL in the provider configuration.',
            $sut->getMessage()
        );
    }
}
