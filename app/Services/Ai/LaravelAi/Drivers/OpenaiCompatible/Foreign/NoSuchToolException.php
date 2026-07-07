<?php

namespace App\Services\Ai\LaravelAi\Drivers\OpenaiCompatible\Foreign;

use Laravel\Ai\Exceptions\AiException;

class NoSuchToolException extends AiException
{
    public function __construct(public readonly string $toolName)
    {
        parent::__construct(sprintf("Model tried to call unavailable tool '%s'.", $toolName));
    }
}
