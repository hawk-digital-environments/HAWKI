<?php

declare(strict_types=1);

namespace App\Services\Users\Exceptions;

/**
 * Thrown when two user-settings classes declare the same `publicKey()` — the keys must
 * be globally unique across the registry, because the JSON:API schema fields and the
 * namespace-resource attributes are keyed by them.
 */
class DuplicateUserSettingsKeyException extends \LogicException implements UsersExceptionInterface
{
    /**
     * Creates the exception for a `publicKey()` already claimed by another settings class.
     */
    public static function forPublicKey(string $publicKey, string $existingClass, string $newClass): self
    {
        return new self(\sprintf(
            'The user-settings public key "%s" is already registered by "%s" and cannot be reused by "%s".'
            . ' Public keys must be globally unique across all settings classes.',
            $publicKey,
            $existingClass,
            $newClass,
        ));
    }
}
