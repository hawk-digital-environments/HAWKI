<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Exceptions;

use Laravel\Ai\Streaming\Events\Error;

class ChatStreamFailedException extends \RuntimeException implements ChatExceptionInterface
{
    public static function fromVendorError(Error $error): self
    {
        return new self(\sprintf(
            'The upstream provider reported a stream error (type "%s"%s): %s',
            $error->type,
            $error->recoverable ? ', recoverable' : '',
            $error->message,
        ));
    }
}
