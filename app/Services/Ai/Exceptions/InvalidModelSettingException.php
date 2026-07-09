<?php
declare(strict_types=1);


namespace App\Services\Ai\Exceptions;


class InvalidModelSettingException extends \InvalidArgumentException implements AiExceptionInterface
{
    public static function forUndeclaredKey(string $key): self
    {
        return new self(sprintf('Setting "%s" is not declared in the ModelSettingRegistry.', $key));
    }
}
