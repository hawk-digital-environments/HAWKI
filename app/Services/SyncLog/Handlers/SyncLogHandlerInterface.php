<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers;


use App\Services\SyncLog\Value\SyncLogEntryConstraints;
use App\Services\SyncLog\Value\SyncLogEntryTypeEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

interface SyncLogHandlerInterface
{
    /**
     * MUST return the type of the log entry this handler is responsible for.
     * @return SyncLogEntryTypeEnum
     */
    public function getType(): SyncLogEntryTypeEnum;

    /**
     * MUST return a list of events and their respective listeners
     * @return array<string, callable|callable[]> The keys are the event names, and the values are the listener methods.
     * The listener methods should be callable and will be invoked when the event is fired.
     * The listener MAY return a {@see SyncLogPayload} that will be used to create the log entries.
     * You can return a single callable or an array of callables for each event.
     */
    public function listeners(): array;

    /**
     * MUST convert the model, this handler is responsible for, to a {@see JsonResource}.
     * @param Model $model
     * @return JsonResource
     */
    public function convertModelToResource(Model $model): JsonResource;

    /**
     * MUST return the model instance by its ID.
     * @param int $id
     * @return Model|null
     */
    public function findModelById(int $id): ?Model;

    /**
     * MUST extract the ID from the given model.
     * This is used to determine the target ID for the log entry.
     * If the model does not have an ID, return 0.
     * @param Model $model
     * @return int
     */
    public function getIdOfModel(Model $model): int;

    /**
     * MUST return the count of entries for a full sync based on the given constraints.
     *
     * @param SyncLogEntryConstraints $constraints
     * @return int
     */
    public function findCountForFullSync(SyncLogEntryConstraints $constraints): int;

    /**
     * MUST return a collection of models for a full sync based on the given constraints.
     *
     * @param SyncLogEntryConstraints $constraints
     * @return Collection<Model>
     */
    public function findModelsForFullSync(SyncLogEntryConstraints $constraints): Collection;
}
