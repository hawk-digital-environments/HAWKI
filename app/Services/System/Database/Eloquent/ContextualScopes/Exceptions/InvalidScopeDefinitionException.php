<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\ContextualScopes\Exceptions;

/**
 * Thrown when a scope registered via {@see \App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar::addScope()}
 * cannot be resolved into a valid {@see \Illuminate\Database\Eloquent\Scope} instance.
 *
 * This always indicates a programming error — the scope key was registered but no resolvable
 * definition was found, or the resolved value was not a Scope.
 */
class InvalidScopeDefinitionException extends \InvalidArgumentException implements ScopeExceptionInterface
{
    /**
     * Creates the exception for a scope key that has no registered definition.
     */
    public static function forMissingDefinition(string $scopeKey, string $modelClass): self
    {
        return new self(sprintf(
            "No scope definition found for scope key '%s' in model '%s'.",
            $scopeKey,
            $modelClass,
        ));
    }

    /**
     * Creates the exception when the resolved scope value is not a {@see \Illuminate\Database\Eloquent\Scope} instance.
     */
    public static function forInvalidResolvedValue(string $scopeKey, string $modelClass, mixed $value): self
    {
        $type = is_object($value) ? get_class($value) : gettype($value);
        return new self(sprintf(
            "Scope definition for scope key '%s' in model '%s' must resolve to an instance of Scope. Got: %s",
            $scopeKey,
            $modelClass,
            $type,
        ));
    }

    /**
     * Creates the exception when the scope definition is not a supported type
     * (must be a {@see \Illuminate\Database\Eloquent\Scope} instance, class-name string, or Closure).
     */
    public static function forInvalidDefinitionType(string $scopeKey, string $modelClass): self
    {
        return new self(sprintf(
            "Invalid scope definition for scope key '%s' in model '%s'. Expected instance of Scope, Closure, or class name string.",
            $scopeKey,
            $modelClass,
        ));
    }
}
