<?php
declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use App\Services\Ai\LaravelAi\ExtendedAiManager;

class InvalidAiManagerException extends \RuntimeException implements AiExceptionInterface
{
    public static function forNotExtendedManager(): self
    {
        return new self(sprintf(
            'AiManager must be an instance of %s, but a different implementation is bound. '
            . 'Ensure the service provider registers %s correctly.',
            ExtendedAiManager::class,
            ExtendedAiManager::class
        ));
    }
}
