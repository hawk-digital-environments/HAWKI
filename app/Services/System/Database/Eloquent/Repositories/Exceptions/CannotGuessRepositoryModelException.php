<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories\Exceptions;

/**
 * Thrown when {@see \App\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTrait}
 * cannot resolve the Eloquent model class for a repository after exhausting all fallback strategies.
 *
 * This is always a programming error. Fix it by annotating the repository class with the
 * {@see \App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel} attribute pointing
 * to the correct model class.
 */
class CannotGuessRepositoryModelException extends \LogicException implements RepositoryExceptionInterface
{
    /**
     * Creates the exception when no resolution strategy succeeds for the given repository.
     */
    public static function forRepository(string $repositoryClass, string $useModelAttributeClass): self
    {
        return new self(sprintf(
            'Could not guess model class for repository "%s". Please specify the model class using the "%s" attribute.',
            $repositoryClass,
            $useModelAttributeClass,
        ));
    }

    /**
     * Creates the exception when the repository uses Laravel's built-in {@code UseModel} attribute
     * (from {@code Illuminate\Database\Eloquent\Factories\Attributes}) instead of HAWKI's own
     * {@see \App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel} attribute.
     */
    public static function forWrongUseModelAttribute(
        string $repositoryClass,
        string $wrongAttributeClass,
        string $correctAttributeClass
    ): self
    {
        return new self(sprintf(
            'The class "%s" has the "%s" attribute, but it is the wrong one. Did you import the correct "%s" attribute?',
            $repositoryClass,
            $wrongAttributeClass,
            $correctAttributeClass,
        ));
    }
}
