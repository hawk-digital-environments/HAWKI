<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories\Exceptions;

use Illuminate\Database\Eloquent\Model;

/**
 * Thrown when a repository's resolved model class does not extend {@see \Illuminate\Database\Eloquent\Model}.
 *
 * This is a programming error — the class resolved by {@see GuessesModelNameTrait} or supplied via
 * the {@see UseModel} attribute must be a valid Eloquent model. Fix by pointing the attribute to
 * the correct model class.
 */
class InvalidRepositoryModelClassException extends \LogicException implements RepositoryExceptionInterface
{
    /**
     * Creates the exception for a class that exists but is not an Eloquent Model subclass.
     */
    public static function forNonEloquentClass(string $modelClass): self
    {
        return new self(sprintf(
            'Model class "%s" must be an instance of %s.',
            $modelClass,
            Model::class,
        ));
    }
}
