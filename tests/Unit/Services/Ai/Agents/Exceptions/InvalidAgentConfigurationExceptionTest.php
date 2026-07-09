<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Exceptions;

use App\Services\Ai\Agents\Exceptions\AgentExceptionInterface;
use App\Services\Ai\Agents\Exceptions\InvalidAgentConfigurationException;
use Laravel\Ai\Messages\Message;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(InvalidAgentConfigurationException::class)]
class InvalidAgentConfigurationExceptionTest extends TestCase
{
    // =========================================================================

    public function testItIsInvalidArgumentException(): void
    {
        $sut = InvalidAgentConfigurationException::forMissingPromptOrMessages();
        static::assertInstanceOf(\InvalidArgumentException::class, $sut);
    }

    public function testItImplementsAgentExceptionInterface(): void
    {
        $sut = InvalidAgentConfigurationException::forMissingPromptOrMessages();
        static::assertInstanceOf(AgentExceptionInterface::class, $sut);
    }

    // =========================================================================
    // forMissingPromptOrMessages
    // =========================================================================

    public function testItForMissingPromptOrMessagesMatchesExpectedMessage(): void
    {
        $sut = InvalidAgentConfigurationException::forMissingPromptOrMessages();
        static::assertSame(
            'Either a promptString or a non-empty messages array must be provided to the agent.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forLastMessageNotAMessageInstance
    // =========================================================================

    public function testItForLastMessageNotAMessageInstanceContainsClassName(): void
    {
        $sut = InvalidAgentConfigurationException::forLastMessageNotAMessageInstance();
        static::assertStringContainsString(Message::class, $sut->getMessage());
    }

    public function testItForLastMessageNotAMessageInstanceMatchesExpectedMessage(): void
    {
        $sut = InvalidAgentConfigurationException::forLastMessageNotAMessageInstance();
        static::assertSame(
            sprintf('The last entry in the messages array must be an instance of %s.', Message::class),
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forLastMessageNotUserRole
    // =========================================================================

    public function testItForLastMessageNotUserRoleMatchesExpectedMessage(): void
    {
        $sut = InvalidAgentConfigurationException::forLastMessageNotUserRole();
        static::assertSame(
            'The last message must have the role of "user" when no promptString is provided.',
            $sut->getMessage()
        );
    }

    // =========================================================================
    // forLastMessageEmptyContent
    // =========================================================================

    public function testItForLastMessageEmptyContentMatchesExpectedMessage(): void
    {
        $sut = InvalidAgentConfigurationException::forLastMessageEmptyContent();
        static::assertSame(
            'The last message must have non-empty content when no promptString is provided.',
            $sut->getMessage()
        );
    }
}
