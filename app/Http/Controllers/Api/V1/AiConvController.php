<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;

class AiConvController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\Store;
    use Actions\Update;
    use Actions\Destroy;

    public function __construct(
        protected readonly MessageContentValidator $contentValidator,
        protected readonly PrivateMessageHandler   $messageHandler
    )
    {
    }

    /**
     * The database cascade would remove the message rows anyway, but the
     * attachments must be deleted through the model so their stored files
     * are cleaned up as well.
     */
    public function deleting(AiConv $conv): void
    {
        foreach ($conv->messages()->with('attachments')->get() as $message) {
            foreach ($message->attachments as $attachment) {
                $attachment->delete();
            }
            $message->delete();
        }
    }

    public function storeMessage(Request $request, AiConv $conv): DataResponse
    {
        Gate::authorize('update', $conv);

        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'threadId' => 'required|integer|min:0',
            'content' => 'required|array',
            'metadata' => 'nullable|array',
            'model' => 'nullable|string|required_if_accepted:isAi',
            'completion' => 'required|boolean',
        ]);
        $validatedData['content'] = $this->contentValidator->validate($validatedData['content']);

        $message = $this->messageHandler->create($conv, $validatedData, $request->user());

        return DataResponse::make($message)->didCreate();
    }

    public function updateMessage(Request $request, AiConv $conv, string $messageId): DataResponse
    {
        Gate::authorize('update', $conv);

        $validatedData = $request->validate([
            'content' => 'required|array',
            'metadata' => 'nullable|array',
            'model' => 'nullable|string',
            'completion' => 'required|boolean',
        ]);
        $validatedData['content'] = $this->contentValidator->validate($validatedData['content']);
        $validatedData['message_id'] = $messageId;
        $validatedData['model'] ??= null;

        $message = $this->messageHandler->update($conv, $validatedData);
        if ($message === null) {
            abort(404, 'The message does not exist in this conversation.');
        }

        return DataResponse::make($message);
    }

    public function deleteMessage(AiConv $conv, string $messageId): Response
    {
        Gate::authorize('update', $conv);

        if (!$this->messageHandler->delete($conv, ['message_id' => $messageId])) {
            abort(404, 'The message does not exist in this conversation.');
        }

        return response()->noContent();
    }

    public function storeAttachment(Request $request, FileStorageService $fileStorage): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        $validatedData = $request->validate([
            'file' => 'required|file|max:' . ($fileStorage->getMaxFileSize() / 1024)
        ]);

        $storedFile = $fileStorage->storeTemporary(
            file: FileReference::fromUploadedFile($validatedData['file']),
            category: StoredFileCategory::PRIVATE,
        );

        if ($storedFile === null) {
            abort(500, 'Failed to store the file.');
        }

        return response()->json([
            'uuid' => $storedFile->getUuid(),
        ], 201);
    }

    public function deleteAttachment(Request $request, string $uuid): Response
    {
        abort_unless($request->user() !== null, 401);

        $attachment = Attachment::where('uuid', $uuid)->firstOrFail();

        if ($attachment->user && !$attachment->user->is($request->user())) {
            abort(403);
        }
        if (!$attachment->attachable instanceof AiConvMsg) {
            abort(422, 'The file is not attached to a private conversation message.');
        }

        $attachment->delete();

        return response()->noContent();
    }
}
