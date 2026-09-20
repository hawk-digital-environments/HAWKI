<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Events\RoomMessageEvent;
use App\Http\Controllers\Controller;
use App\Jobs\GenerateRoomAiResponse;
use App\Models\Room;
use App\Services\Ai\Chat\ChatService;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Exceptions\ModelIdNotAvailableException;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\UnknownModelException;
use App\Services\Ai\Formatters\Exceptions\UnknownToolCallException;
use App\Services\Ai\Formatters\FormatterRegistry;
use App\Services\Ai\Models\Repositories\AiModelRepository;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use Illuminate\Http\Request;
use Laravel\Ai\Exceptions\NoSuchToolException;
use Symfony\Component\HttpFoundation\Response;

/**
 * The HAWKI UI chat entry point (proposal Phase 5): the merged construct for the
 * HAWKI frontend, session-authenticated (`auth` + `expiry_check` + `signature_check`).
 *
 * - `POST /api/hawki/v1/ui-chat` — private request: streams the response directly,
 *   in the native `openResponses` wire format. Conversation persistence stays with
 *   the client (AiConv flow), usage records under channel `ui-chat`.
 * - `POST /api/hawki/v1/ui-chat/{slug}` — group request (`roomEditor`-authorized):
 *   generation + encryption + persistence + broadcast run queued
 *   ({@see GenerateRoomAiResponse}); the caller receives `{"success": true}` and the
 *   AI message reaches every room member through Reverb. The body is the same
 *   openResponses request plus the `hawki` orchestration fields (`threadIndex`,
 *   `messageId`, `isUpdate`, `key`); a `stream` flag is accepted but ignored — group
 *   responses are delivered via broadcast, never streamed to the caller.
 */
class UiChatController extends Controller
{
    public function __construct(
        private readonly FormatterRegistry $formatters,
        private readonly ChatService $chatService,
        private readonly AiModelRepository $modelRepository,
    ) {
    }

    public function private(Request $request): Response
    {
        $formatter = $this->formatters->resolve();

        try {
            $aiRequest = $formatter->parseRequest($request);
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        }

        $aiRequest = $this->asUiChatRequest($aiRequest);

        if ($aiRequest->wantsStreaming()) {
            try {
                return $formatter->formatStream($this->chatService->sendStreaming($aiRequest));
            } catch (ModelIdNotAvailableException $exception) {
                return $formatter->formatError(UnknownModelException::fromModelException($exception));
            }
        }

        try {
            return $formatter->formatResponse($this->chatService->send($aiRequest));
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        } catch (ModelIdNotAvailableException $exception) {
            return $formatter->formatError(UnknownModelException::fromModelException($exception));
        } catch (NoSuchToolException $exception) {
            return $formatter->formatError(UnknownToolCallException::fromVendorException($exception));
        }
    }

    public function group(Request $request, string $slug): Response
    {
        $formatter = $this->formatters->resolve();

        try {
            $aiRequest = $formatter->parseRequest($request);
        } catch (FormatterRequestException $exception) {
            return $formatter->formatError($exception);
        }

        if (null === $aiRequest->model || '' === $aiRequest->model) {
            return response()->json(['error' => 'A model is required for group chat requests.'], 400);
        }

        $validated = $request->validate([
            'hawki.threadIndex' => ['nullable', 'integer'],
            'hawki.messageId' => ['nullable', 'string'],
            'hawki.isUpdate' => ['nullable', 'boolean'],
            'hawki.key' => ['required', 'string'],
        ]);

        $room = Room::where('slug', $slug)->firstOrFail();

        try {
            $model = $this->modelRepository->findOneOrFail($aiRequest->model);
        } catch (\Throwable) {
            return response()->json(['error' => 'The requested model is not available.'], 400);
        }

        $aiRequest = $this->asUiChatRequest($aiRequest)
            ->withHawkiExtension(AiRequest::HAWKI_EXTENSION_BROADCAST, true);

        RoomAiWritingStartedEvent::dispatch($room, $model);

        broadcast(new RoomMessageEvent([
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => true,
                'model' => $model->model_id,
            ],
        ]));

        GenerateRoomAiResponse::dispatch($room->id, $model->id, $aiRequest, [
            'threadIndex' => $validated['hawki']['threadIndex'] ?? 0,
            'messageId' => $validated['hawki']['messageId'] ?? null,
            'isUpdate' => (bool)($validated['hawki']['isUpdate'] ?? false),
            'key' => $validated['hawki']['key'],
        ]);

        return response()->json(['success' => true]);
    }

    private function asUiChatRequest(AiRequest $request): AiRequest
    {
        return $request->withHawkiExtension(AiRequest::HAWKI_EXTENSION_CHANNEL, 'ui-chat');
    }
}
