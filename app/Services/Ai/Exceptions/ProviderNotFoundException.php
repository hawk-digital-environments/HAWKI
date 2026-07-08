<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class ProviderNotFoundException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forInput(string|int $input): self
    {
        return new self(sprintf('Could not find AI provider with identifier "%s".', $input));
    }
}
