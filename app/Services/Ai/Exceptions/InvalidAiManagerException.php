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

    public static function forUnsupportedDefaultInstance(): self
    {
        return new self(sprintf(
            '%s does not support resolving a default instance. '
            . 'Providers are resolved explicitly by name — there is no single default AI provider.',
            ExtendedAiManager::class,
        ));
    }
}
