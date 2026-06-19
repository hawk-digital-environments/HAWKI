<?php
declare(strict_types=1);

namespace App\Services\System\Container\Exceptions;

/**
 * Thrown by {@see \App\Services\System\Container\ServiceLocator} when a service or callback
 * cannot be resolved because no container is available.
 */
class ServiceLocatorException extends \RuntimeException implements ContainerExceptionInterface
{
    public static function becauseServiceNotFound(string $id): self
    {
        return new self(sprintf(
            'Service with id "%s" not found in ServiceLocator and no container available to resolve it.',
            $id,
        ));
    }

    public static function becauseCallbackHasNoContainer(string $executionId): self
    {
        return new self(sprintf(
            'Failed to execute callback with id "%s". There are neither defined execution params nor an application instance available.',
            $executionId,
        ));
    }
}
