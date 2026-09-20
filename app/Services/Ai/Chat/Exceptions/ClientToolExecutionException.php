<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Exceptions;

class ClientToolExecutionException extends \RuntimeException implements ChatExceptionInterface
{
    public static function forTool(string $toolName): self
    {
        return new self(\sprintf(
            'The client tool "%s" cannot be executed server-side: client tools are handed off to the requesting client via the tool-call pause and are never invoked in the HAWKI runtime.',
            $toolName,
        ));
    }
}
