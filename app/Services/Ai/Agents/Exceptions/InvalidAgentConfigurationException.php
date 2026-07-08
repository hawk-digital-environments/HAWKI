<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

use Laravel\Ai\Messages\Message;

class InvalidAgentConfigurationException extends \InvalidArgumentException implements AgentExceptionInterface
{
    public static function forMissingPromptOrMessages(): self
    {
        return new self('Either a promptString or a non-empty messages array must be provided to the agent.');
    }

    public static function forLastMessageNotAMessageInstance(): self
    {
        return new self(sprintf(
            'The last entry in the messages array must be an instance of %s.',
            Message::class
        ));
    }

    public static function forLastMessageNotUserRole(): self
    {
        return new self('The last message must have the role of "user" when no promptString is provided.');
    }

    public static function forLastMessageEmptyContent(): self
    {
        return new self('The last message must have non-empty content when no promptString is provided.');
    }
}
