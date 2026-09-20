<?php

namespace App\Jobs;

use App\Models\Ai\AiModel;
use App\Models\Room;
use App\Services\Chat\RoomAiResponseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queues the group-chat AI generation for a room request. Replaces the former
 * register_shutdown_function construct: the HTTP response returns immediately while
 * the generation, encryption, persistence and broadcast run on the queue.
 *
 * Carries only scalar ids and the validated payload array — no serialized models, no
 * auth state. Runs single-attempt with a generous timeout so a crashed worker cannot
 * produce duplicate AI messages in a room.
 */
class GenerateRoomAiResponse implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public int $roomId,
        public int $modelId,
        public array $validatedPayload,
    ) {
    }

    public function handle(RoomAiResponseService $service): void
    {
        // Queue context has no request scope context — resolve deterministically by
        // primary key, bypassing contextual scopes (availability was checked at dispatch).
        $room = Room::query()->withoutGlobalScopes()->findOrFail($this->roomId);
        $model = AiModel::query()->withoutGlobalScopes()->findOrFail($this->modelId);

        $service->generate($room, $model, $this->validatedPayload);
    }
}
