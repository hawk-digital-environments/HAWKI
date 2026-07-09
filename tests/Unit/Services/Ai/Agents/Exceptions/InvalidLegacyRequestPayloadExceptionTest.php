<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\InvalidLegacyRequestPayloadException;
use Laravel\Ai\Messages\MessageRole;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidLegacyRequestPayloadException::class)]
class InvalidLegacyRequestPayloadExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsInvalidArgumentException(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forMissingSystemInstructions();
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forMissingSystemInstructions();
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forMissingSystemInstructions
    // =========================================================================

    public function testItForMissingSystemInstructionsMatchesExpectedMessage(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forMissingSystemInstructions();
        static::assertSame(
            'No system instructions found in messages payload.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forMessageMissingFields
    // =========================================================================

    public function testItForMessageMissingFieldsMatchesExpectedMessage(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forMessageMissingFields();
        static::assertSame(
            'Each message must have a "role" and "content.text" field.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forInvalidMessageRole
    // =========================================================================

    public function testItForInvalidMessageRoleContainsTheRole(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forInvalidMessageRole('system');
        static::assertStringContainsString('system', $sut->getMessage());
    }

    public function testItForInvalidMessageRoleContainsAllowedRoles(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forInvalidMessageRole('system');
        static::assertStringContainsString(MessageRole::User->value, $sut->getMessage());
        static::assertStringContainsString(MessageRole::Assistant->value, $sut->getMessage());
    }

    public function testItForInvalidMessageRoleMatchesExpectedMessage(): void
    {
        $sut = InvalidLegacyRequestPayloadException::forInvalidMessageRole('system');
        static::assertSame(
            sprintf(
                'Invalid message role "%s". Allowed roles are "%s" and "%s".',
                'system',
                MessageRole::User->value,
                MessageRole::Assistant->value
            ),
            $sut->getMessage()
        );
    }
}
