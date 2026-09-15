<?php
declare(strict_types=1);

namespace App\Services\Ai\Agents\Exceptions;

use Laravel\Ai\Messages\MessageRole;

class InvalidLegacyRequestPayloadException extends \InvalidArgumentException implements AgentExceptionInterface
{
    public static function forMissingSystemInstructions(): self
    {
        return new self('No system instructions found in messages payload.');
    }

    public static function forMessageMissingFields(): self
    {
        return new self('Each message must have a "role" and "content.text" field.');
    }

    public static function forInvalidMessageRole(string $role): self
    {
        return new self(sprintf(
            'Invalid message role "%s". Allowed roles are "%s" and "%s".',
            $role,
            MessageRole::User->value,
            MessageRole::Assistant->value
        ));
    }
}
