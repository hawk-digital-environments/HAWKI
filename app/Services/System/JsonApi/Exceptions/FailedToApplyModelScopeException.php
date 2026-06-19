<?php
declare(strict_types=1);

namespace App\Services\System\JsonApi\Exceptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use LaravelJsonApi\Eloquent\Schema;

/**
 * Thrown when {@see \App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaServerTrait}
 * cannot register a scope declared by a {@see \App\Services\System\JsonApi\Eloquent\ScopedSchema\ScopedSchemaInterface}
 * schema as an Eloquent global scope on the model.
 */
class FailedToApplyModelScopeException extends \RuntimeException implements JsonApiExceptionInterface
{
    public static function forInvalidSchemaClass(string $schema): self
    {
        return new self(sprintf(
            'Schema class "%s" does not extend %s. Model scopes only work with Eloquent schemas.',
            $schema,
            Schema::class
        ));
    }

    public static function forMissingScopeClass(string $scopeClass, string $schema): self
    {
        return new self(sprintf(
            'Scope class "%s" does not exist. Could not apply model scope for schema "%s".',
            $scopeClass,
            $schema
        ));
    }

    public static function forInvalidScopeClass(string $scopeClass, string $schema): self
    {
        return new self(sprintf(
            'Scope class "%s" does not implement %s. Could not apply model scope for schema "%s".',
            $scopeClass,
            Scope::class,
            $schema
        ));
    }

    public static function forMissingModelClass(string $modelClass, string $schema): self
    {
        return new self(sprintf(
            'Model class "%s" does not exist. Could not apply model scope for schema "%s".',
            $modelClass,
            $schema
        ));
    }

    public static function forInvalidModelClass(string $modelClass, string $schema): self
    {
        return new self(sprintf(
            'Model class "%s" does not implement %s. Could not apply model scope for schema "%s".',
            $modelClass,
            Model::class,
            $schema
        ));
    }
}
