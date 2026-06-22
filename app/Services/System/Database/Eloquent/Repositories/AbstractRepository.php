<?php
declare(strict_types=1);

namespace App\Services\System\Database\Eloquent\Repositories;

use App\Services\System\Database\Eloquent\Repositories\Traits\GuessesModelNameTrait;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;

/**
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
     * @return class-string<TModel>
     */
    public function getModelClass(): string
    {
        return $this->guessedModelClass ??= $this->guessModelName();
    }

    /**
     * @return TModel|null
     */
    public function findOne(mixed $id): ?Model
    {
        return $this->getQuery()->find($id);
    }

    /**
     * @return Collection<int, TModel>
     */
    public function findAll(): Collection
    {
        /** @var Collection<int, TModel> */
        return $this->getQuery()->get();
    }

    /**
     * @return LazyCollection<int, TModel>
     */
    public function findAllLazy(): LazyCollection
    {
        return $this->getQuery()->lazy();
    }

    /**
     * @return TModel
     */
    protected function getEloquentInstance(): Model
    {
        if ($this->modelInstance === null) {
            /** @var class-string<TModel> $modelClass */
            $modelClass = $this->getModelClass();
            if (!is_a($modelClass, Model::class, true)) {
                // @todo exception
                throw new \LogicException(
                    sprintf('Model class "%s" must be an instance of %s.', $modelClass, Model::class)
                );
            }
            $this->modelInstance = new $modelClass();
        }
        return $this->modelInstance;
    }

    /**
     * @return Builder<TModel>
     */
    protected function getQuery(): Builder
    {
        /** @var Builder<TModel> */
        return $this->getEloquentInstance()->newQuery();
    }

    /**
     * @return Builder<TModel>
     */
    protected function getQueryWithoutAnyScopes(): Builder
    {
        /** @var Builder<TModel> */
        return $this->getEloquentInstance()->newQueryWithoutScopes();
    }
}
