<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories\Exceptions;

class CannotGuessRepositoryModelException extends \LogicException implements RepositoryExceptionInterface
{
    public static function forRepository(string $repositoryClass, string $useModelAttributeClass): self
    {
        return new self(sprintf(
            'Could not guess model class for repository "%s". Please specify the model class using the "%s" attribute.',
            $repositoryClass,
            $useModelAttributeClass,
        ));
    }

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
