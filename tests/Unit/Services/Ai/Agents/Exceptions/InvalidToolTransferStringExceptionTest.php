<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\InvalidToolTransferStringException;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidToolTransferStringException::class)]
class InvalidToolTransferStringExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsInvalidArgumentException(): void
    {
        $sut = InvalidToolTransferStringException::forNotAString();
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = InvalidToolTransferStringException::forNotAString();
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forNotAString
    // =========================================================================

    public function testItForNotAStringMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forNotAString();
        static::assertSame(
            'Tool transfer strings must be an array of strings.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidType
    // =========================================================================

    public function testItForInvalidTypeContainsTheTransferString(): void
    {
        $sut = InvalidToolTransferStringException::forInvalidType('unknown:foo');
        static::assertStringContainsString('unknown:foo', $sut->getMessage());
    }

    public function testItForInvalidTypeMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forInvalidType('unknown:foo');
        static::assertSame(
            'Tool transfer string "unknown:foo" must describe either a capability or a tool name.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forCapabilityNotFound
    // =========================================================================

    public function testItForCapabilityNotFoundContainsTheCapabilityKey(): void
    {
        $sut = InvalidToolTransferStringException::forCapabilityNotFound('web_search');
        static::assertStringContainsString('web_search', $sut->getMessage());
    }

    public function testItForCapabilityNotFoundMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forCapabilityNotFound('web_search');
        static::assertSame('Capability "web_search" is not registered.', $sut->getMessage());
    }

    // =========================================================================
    // forCapabilityMissingInnerTool
    // =========================================================================

    public function testItForCapabilityMissingInnerToolContainsTheCapabilityKey(): void
    {
        $sut = InvalidToolTransferStringException::forCapabilityMissingInnerTool('web_search');
        static::assertStringContainsString('web_search', $sut->getMessage());
    }

    public function testItForCapabilityMissingInnerToolMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forCapabilityMissingInnerTool('web_search');
        static::assertSame(
            'Capability "web_search" requires an inner tool to be specified in the transfer string.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forSettingsNotJsonObject
    // =========================================================================

    public function testItForSettingsNotJsonObjectContainsTheSettingsString(): void
    {
        $sut = InvalidToolTransferStringException::forSettingsNotJsonObject('[1,2,3]');
        static::assertStringContainsString('[1,2,3]', $sut->getMessage());
    }

    public function testItForSettingsNotJsonObjectMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forSettingsNotJsonObject('[1,2,3]');
        static::assertSame(
            'Settings in tool transfer string must be a JSON object, got: "[1,2,3]".',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidJsonSettings
    // =========================================================================

    public function testItForInvalidJsonSettingsContainsTheSettingsString(): void
    {
        $previous = new \JsonException('Syntax error');
        $sut = InvalidToolTransferStringException::forInvalidJsonSettings('{bad json}', $previous);
        static::assertStringContainsString('{bad json}', $sut->getMessage());
    }

    public function testItForInvalidJsonSettingsMatchesExpectedMessage(): void
    {
        $previous = new \JsonException('Syntax error');
        $sut = InvalidToolTransferStringException::forInvalidJsonSettings('{bad json}', $previous);
        static::assertSame(
            'Invalid JSON settings in tool transfer string: "{bad json}".',
            $sut->getMessage()
        );
    }

    public function testItForInvalidJsonSettingsChainsPreviousException(): void
    {
        $previous = new \JsonException('Syntax error');
        $sut = InvalidToolTransferStringException::forInvalidJsonSettings('{bad json}', $previous);
        static::assertSame($previous, $sut->getPrevious());
    }

    // =========================================================================
    // forMissingCapabilityOrToolName
    // =========================================================================

    public function testItForMissingCapabilityOrToolNameContainsTheTransferString(): void
    {
        $sut = InvalidToolTransferStringException::forMissingCapabilityOrToolName('capability::');
        static::assertStringContainsString('capability::', $sut->getMessage());
    }

    public function testItForMissingCapabilityOrToolNameMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forMissingCapabilityOrToolName('capability::');
        static::assertSame(
            'Tool transfer string "capability::" is missing the capability name or inner tool name.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forEmptyToolName
    // =========================================================================

    public function testItForEmptyToolNameContainsTheTransferString(): void
    {
        $sut = InvalidToolTransferStringException::forEmptyToolName(':{"key":"val"}');
        static::assertStringContainsString(':{"key":"val"}', $sut->getMessage());
    }

    public function testItForEmptyToolNameMatchesExpectedMessage(): void
    {
        $sut = InvalidToolTransferStringException::forEmptyToolName(':{"key":"val"}');
        static::assertSame(
            'Tool transfer string ":{"key":"val"}" does not contain a tool name.',
            $sut->getMessage()
        );
    }
}
