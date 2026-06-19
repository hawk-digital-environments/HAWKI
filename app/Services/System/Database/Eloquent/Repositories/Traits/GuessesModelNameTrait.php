<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Traits;


use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel as WrongAttribute;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * @psalm-require-extends AbstractRepository
 */
trait GuessesModelNameTrait
{
    protected function guessModelName(): string
    {
        $potentialModelClass =
            $this->resolveModelClassFromAttribute(static::class)
            ?? $this->resolveModelClassFromExtendsAnnotation(static::class)
            ?? $this->resolveModelClassFromRepositoryClassName(static::class);

        if ($potentialModelClass === null) {
            throw new \LogicException(sprintf(
                'Could not guess model class for repository "%s". Please specify the model class using the "%s" attribute.',
                static::class,
                UseModel::class
            ));
        }

        return $potentialModelClass;
    }

    private function resolveModelClassFromAttribute(string $class): ?string
    {
        if (!class_exists($class)) {
            return null;
        }

        $attributes = (new ReflectionClass($class))->getAttributes(UseModel::class);
        if (empty($attributes)) {
            // Special check, there is another "UseModel" attribute in Laravel, so we want to show the user that it is the wrong one
            $wrongAttributes = (new ReflectionClass($class))->getAttributes(WrongAttribute::class);
            if (!empty($wrongAttributes)) {
                throw new \LogicException(sprintf(
                    'The class "%s" has the "%s" attribute, but it is the wrong one. Did you import the correct "%s" attribute?',
                    $class,
                    WrongAttribute::class,
                    UseModel::class
                ));
            }
        }

        $potentialModelClass = $attributes !== []
            ? $attributes[0]->newInstance()->class
            : null;

        return $this->isValidModelClass($potentialModelClass) ? $potentialModelClass : null;
    }

    private function resolveModelClassFromExtendsAnnotation(string $class): ?string
    {
        $pattern = '/@extends.*?<([a-zA-Z0-9_\\\\]+)>/';
        $docComment = (new ReflectionClass($class))->getDocComment();
        $matches = [];
        if (preg_match($pattern, $docComment ?: '', $matches)) {
            $potentialModelClass = $matches[1];
            if (str_contains($potentialModelClass, '\\') && $this->isValidModelClass($potentialModelClass)) {
                return $potentialModelClass;
            }
            $potentialModelClass = $this->inferFullyQualifiedModelName($potentialModelClass);
            if ($this->isValidModelClass($potentialModelClass)) {
                return $potentialModelClass;
            }
        }
        return null;
    }

    private function resolveModelClassFromRepositoryClassName(string $repositoryClass): ?string
    {
        // @todo this becomes relevant when we start working with plugins, currently this is fine for our app structure, tho.
        if (!str_starts_with($repositoryClass, 'App\\')) {
            return null;
        }

        $potentialModelClass = $this->inferFullyQualifiedModelName(
            str_replace('Repository', '', class_basename($repositoryClass))
        );

        if ($this->isValidModelClass($potentialModelClass)) {
            return $potentialModelClass;
        }

        // If repository in App\Services\$domain\Repositories, we also want to check App\Models\$domain\$model
        $pattern = '/App\\\\Services\\\\(.*?)\\\\Repositories/';
        $matches = [];
        if (preg_match($pattern, $repositoryClass, $matches)) {
            $potentialModelClass = $this->inferFullyQualifiedModelName(
                $matches[1] . '\\' . str_replace('Repository', '', class_basename($repositoryClass))
            );
            if ($this->isValidModelClass($potentialModelClass)) {
                return $potentialModelClass;
            }
        }

        return null;
    }

    private function isValidModelClass(?string $class): bool
    {
        return $class !== null && class_exists($class) && is_a($class, Model::class, true);
    }

    private function inferFullyQualifiedModelName(string $potentialModelClass): string
    {
        return sprintf('App\\Models\\%s', $potentialModelClass);
    }
}
