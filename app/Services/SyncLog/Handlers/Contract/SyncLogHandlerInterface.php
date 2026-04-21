<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Handlers\Contract;


use App\Services\SyncLog\Value\SyncLogEntryType;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 *
 * @template T
 */
interface SyncLogHandlerInterface
{
    /**
     * MUST return the type of the log entry this handler is responsible for.
     * @return SyncLogEntryType
     */
    public function getType(): SyncLogEntryType;
    
    /**
     * MUST convert the model, this handler is responsible for, to a {@see JsonResource}.
     * @param T $model
     * @return JsonResource
     */
    public function convertModelToResource(mixed $model): JsonResource;
    
    /**
     * MUST extract the ID from the given model.
     * This is used to determine the target ID for the log entry.
     * If the model does not have an ID, return 0.
     * @param T $model
     * @return int
     */
    public function getIdOfModel(mixed $model): int;
}
