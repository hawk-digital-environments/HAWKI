<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Exceptions;

class ChatAgentNotResolvedException extends \RuntimeException implements ChatExceptionInterface
{
    public static function forMissingDefaultModel(): self
    {
        return new self('No chat agent could be resolved: the request names no model and no system default chat model is configured.');
    }
}
