<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories;

use App\Services\System\Database\Eloquent\Repositories\Exceptions\InvalidRepositoryModelClassException;
use App\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTrait;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
 * Base class for all Eloquent repositories in HAWKI.
 *
 * Repositories are the only location where DB access happens. Services and controllers
 * must never call Eloquent model statics directly — always delegate to a repository.
 *
 * The associated Eloquent model is resolved automatically via {@see GuessesModelNameTrait}
 * using three fallback strategies (tried in order):
 *   1. The {@see UseModel} attribute on the repository class.
 *   2. A `@extends AbstractRepository<MyModel>` DocBlock annotation.
 *   3. The repository class name with the "Repository" suffix stripped, looked up under `App\Models\`.
 *
 * Usage:
 * ```php
 * // Convention-based: UserRepository resolves to App\Models\User automatically.
 * class UserRepository extends AbstractRepository
 * {
 *     public function findByEmail(string $email): ?User
 *     {
 *         return $this->getQuery()->where('email', $email)->first();
 *     }
 * }
 *
 * // Explicit: use #[UseModel] when the repository name does not match the model.
 * #[UseModel(User::class)]
 * class AccountRepository extends AbstractRepository
 * {
 *     // ...
 * }
 * ```
 *
 * @template TModel of Model
 */
#[Singleton]
abstract class AbstractRepository
{
    use GuessesModelNameTrait;

    /**
     * @var TModel|null
     */
    private Model|null $modelInstance = null;
    private string|null $guessedModelClass = null;

    /**
     * Returns the fully-qualified class name of the associated Eloquent model.
     * Auto-guessed on first call via {@see GuessesModelNameTrait} and cached thereafter.
     *
     * @return class-string<TModel>
     */
    public function getModelClass(): string
    {
        return $this->guessedModelClass ??= $this->guessModelName();
    }

    /**
     * Finds a single record by its primary key. Returns null when not found.
     *
     * @return TModel|null
     */
    public function findOne(mixed $id): ?Model
    {
        return $this->getQuery()->find($id);
    }

    /**
     * Retrieves all records as an Eloquent Collection.
     *
     * @return Collection<int, TModel>
     */
    public function findAll(): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->getQuery()->get();
    }

    /**
     * Retrieves all records as a LazyCollection, evaluating rows in chunks
     * to keep memory consumption constant on large tables.
     *
     * @return LazyCollection<int, TModel>
     */
    public function findAllLazy(): LazyCollection
    {
        return $this->getQuery()->lazy();
    }

    /**
     * Returns a cached, bare instance of the model class used for query building.
     *
     * @throws InvalidRepositoryModelClassException when the resolved model class does not extend Eloquent Model.
     * @return TModel
     */
    protected function getEloquentInstance(): Model
    {
        if ($this->modelInstance === null) {
            /** @var class-string<TModel> $modelClass */
            $modelClass = $this->getModelClass();
            if (!is_a($modelClass, Model::class, true)) {
                throw InvalidRepositoryModelClassException::forNonEloquentClass($modelClass);
            }
            $this->modelInstance = new $modelClass();
        }
        return $this->modelInstance;
    }

    /**
     * Returns a fresh Eloquent query builder for the model with all global scopes applied.
     *
     * @return Builder<TModel>
     */
    protected function getQuery(): Builder
    {
        /** @var Builder<TModel> */
        return $this->getEloquentInstance()->newQuery();
    }

    /**
     * Returns a query builder that bypasses every registered global scope.
     * Use only for privileged operations (admin tooling, data migrations) that must see all records.
     *
     * @return Builder<TModel>
     */
    protected function getQueryWithoutAnyScopes(): Builder
    {
        /** @var Builder<TModel> */
        return $this->getEloquentInstance()->newQueryWithoutScopes();
    }
}
