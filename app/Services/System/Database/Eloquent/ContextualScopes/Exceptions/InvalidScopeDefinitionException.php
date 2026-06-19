<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\ContextualScopes\Exceptions;

class InvalidScopeDefinitionException extends \InvalidArgumentException implements ScopeExceptionInterface
{
    public static function forMissingDefinition(string $scopeKey, string $modelClass): self
    {
        return new self(sprintf(
            "No scope definition found for scope key '%s' in model '%s'.",
            $scopeKey,
            $modelClass,
        ));
    }

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

    public static function forInvalidDefinitionType(string $scopeKey, string $modelClass): self
    {
        return new self(sprintf(
            "Invalid scope definition for scope key '%s' in model '%s'. Expected instance of Scope, Closure, or class name string.",
            $scopeKey,
            $modelClass,
        ));
    }
}
