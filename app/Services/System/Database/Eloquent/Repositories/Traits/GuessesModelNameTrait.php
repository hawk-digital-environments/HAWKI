<?php
declare(strict_types=1);


namespace App\Services\System\Database\Eloquent\Repositories\Traits;


use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use App\Services\System\Database\Eloquent\Repositories\Exceptions\CannotGuessRepositoryModelException;
use Illuminate\Database\Eloquent\Factories\Attributes\UseModel as WrongAttribute;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;

/**
 * Resolves the Eloquent model class for a repository using three fallback strategies (tried in order):
 *
 *   1. The {@see UseModel} attribute on the repository class.
 *   2. A `(@)extends AbstractRepository<App\Models\MyModel>` DocBlock annotation without braces.
 *   3. The repository class name with the "Repository" suffix stripped, looked up under `App\Models\`.
 *      For repositories inside `App\Services\{Domain}\Repositories`, the domain prefix is also tried
 *      (e.g. `App\Services\Ai\Repositories\AiModelRepository` -> `App\Models\Ai\AiModel`).
 *
 * @psalm-require-extends AbstractRepository
 */
trait GuessesModelNameTrait
{
    /**
     * Resolves the model class name using the configured strategies in priority order.
     * Throws a {@see \LogicException} when no strategy succeeds, instructing the developer
     * to add a {@see UseModel} attribute.
     */
    protected function guessModelName(): string
    {
        $potentialModelClass =
            $this->resolveModelClassFromAttribute(static::class)
            ?? $this->resolveModelClassFromExtendsAnnotation(static::class)
            ?? $this->resolveModelClassFromRepositoryClassName(static::class);

        if ($potentialModelClass === null) {
            throw CannotGuessRepositoryModelException::forRepository(static::class, UseModel::class);
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
                throw CannotGuessRepositoryModelException::forWrongUseModelAttribute($class, WrongAttribute::class, UseModel::class);
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

        $modelBaseName = str_replace('Repository', '', class_basename($repositoryClass));

        // Extract domain parts from the service namespace.
        // If the repository sits under an explicit \Repositories\ segment, use everything before it.
        // Otherwise fall back to all namespace parts between App\Services\ and the class itself
        // (handles repositories like App\Services\Ai\SystemModels\SystemModelRepository).
        $domainParts = [];
        $matches = [];
        if (preg_match('/App\\\\Services\\\\(.*?)\\\\Repositories/', $repositoryClass, $matches)) {
            if ($matches[1] !== '') {
                $domainParts = explode('\\', $matches[1]);
            }
        } elseif (preg_match('/App\\\\Services\\\\(.+)\\\\[^\\\\]+$/', $repositoryClass, $matches)) {
            $domainParts = explode('\\', $matches[1]);
        }

        // Try from most specific (up to 2 domain layers) down to no domain prefix so that the more specific model wins
        $maxLayers = min(count($domainParts), 2);
        for ($layers = $maxLayers; $layers >= 0; $layers--) {
            $prefix = implode('\\', array_slice($domainParts, 0, $layers));
            $potentialModelClass = $prefix !== ''
                ? $this->inferFullyQualifiedModelName($prefix . '\\' . $modelBaseName)
                : $this->inferFullyQualifiedModelName($modelBaseName);

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
