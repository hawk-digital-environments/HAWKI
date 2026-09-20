<?php

namespace App\Http\Controllers;

use App\Events\RoomMessageEvent;
use App\Jobs\GenerateRoomAiResponse;
use App\Models\Room;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Legacy group-chat AI route (`POST /req/room/streamAI/{slug}`), spoken by the legacy
 * frontend. Wire-compatible with the previous implementation: it validates the legacy
 * payload, marks the room as generating, dispatches the queued generation
 * ({@see GenerateRoomAiResponse}) and returns `{"success": true}` immediately — the
 * response itself reaches the room through Reverb.
 *
 * The LLM call runs through the chat proxy service; the orchestration
 * (encryption, persistence, broadcast) lives in
 * {@see \App\Services\Chat\RoomAiResponseService}. The native equivalent of this
 * route is `POST /api/hawki/v1/ui-chat/{slug}`.
 */
class StreamController extends Controller
{
    public function __construct(
        private readonly AiModelRepository $modelRepository,
        private readonly LegacyFormatter $legacyFormatter,
    )
    {
    }

    public function handleAiConnectionRequest(Request $request)
    {
        try {
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.stream' => 'required|boolean',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'nullable|string',
                'payload.messages.*.content.attachments' => 'nullable|array',
                'payload.tools' => 'nullable|array',
                'payload.params' => 'nullable|array',

                'broadcast' => 'required|boolean',
                'isUpdate' => 'nullable|boolean',
                'messageId' => 'nullable|string',
                'threadIndex' => 'nullable|int',
                'slug' => 'nullable|string',
                'key' => 'nullable|string',
            ]);

            // Ensure that nullable fields are set to default values if not provided
            foreach ($validatedData['payload']['messages'] as &$message) {
                if (isset($message['content']['text']) && !is_string($message['content']['text'])) {
                    $message['content']['text'] = '';
                }
                if (isset($message['content']['attachments']) && !is_array($message['content']['attachments'])) {
                    $message['content']['attachments'] = [];
                }
            }
            unset($message);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        $room = Room::where('slug', $validatedData['slug'])->firstOrFail();
        try {
            $model = $this->modelRepository->findOneOrFail($validatedData['payload']['model']);
        } catch (\Throwable) {
            return response()->json(['error' => 'The requested model is not available.'], 400);
        }

        try {
            $aiRequest = $this->legacyFormatter->parsePayload($validatedData);
        } catch (FormatterRequestException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => ['payload' => [$e->getMessage()]],
            ], 422);
        }

        RoomAiWritingStartedEvent::dispatch($room, $model);

        // Broadcast initial generation status immediately
        broadcast(new RoomMessageEvent([
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => true,
                'model' => $validatedData['payload']['model']
            ]
        ]));

        // The generation runs on the queue: the client already received its response,
        // the AI message is delivered to every room member through Reverb.
        GenerateRoomAiResponse::dispatch($room->id, $model->id, $aiRequest, [
            'threadIndex' => $validatedData['threadIndex'] ?? 0,
            'messageId' => $validatedData['messageId'] ?? null,
            'isUpdate' => (bool)($validatedData['isUpdate'] ?? false),
            'key' => $validatedData['key'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }
}
