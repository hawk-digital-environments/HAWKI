<?php
declare(strict_types=1);


namespace App\Services\SyncLog\Value;


use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

readonly class SyncLogPayload
{
    public function __construct(
        /**
         * The model that should be tracked
         */
        public Model                  $model,

        /**
         * The action that should be tracked for the model.
         * @var SyncLogEntryActionEnum
         */
        public SyncLogEntryActionEnum $action,

        /**
         * A list of users that should be notified about this log entry.
         * This is the audience for the log entry.
         * @var Collection<User>
         */
        public Collection             $audience,

        /**
         * The room for which this log entry is relevant.
         * If the model does not have a room, this can be null.
         * @var Room|null
         */
        public ?Room                  $room = null,
    )
    {
    }
}
