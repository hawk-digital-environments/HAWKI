<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAiConvAttachmentRequest;
use App\Http\Requests\Api\V1\StoreAiConvMessageRequest;
use App\Http\Requests\Api\V1\UpdateAiConvMessageRequest;
use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;
use App\Services\Chat\AiConv\Repositories\AiConvRepository;
use App\Services\Chat\Attachment\Repositories\AttachmentRepository;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Values\FileReference;
use App\Services\Storage\Values\StoredFileCategory;
use App\Services\Storage\Values\StoredFileIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

class AiConvController extends Controller
{
    use Actions\FetchMany;
    use Actions\FetchOne;
    use Actions\Store;
    use Actions\Update;
    use Actions\Destroy;

    public function __construct(
        protected readonly PrivateMessageHandler $messageHandler,
        protected readonly AiConvRepository $conversationRepository,
        protected readonly AttachmentRepository $attachmentRepository,
        protected readonly FileStorageService $fileStorage,
    ) {
    }

    public function creating(ResourceRequest $request, ResourceQuery $query): DataResponse
    {
        $validated = $request->validated();
        $conversation = $this->conversationRepository->create(
            $validated['name'] ?? null,
            $validated['system_prompt'] ?? null,
            $validated['assistant_handle'] ?? null,
        );

        return DataResponse::make($conversation)
            ->withQueryParameters($query)
            ->didCreate();
    }

    public function deleting(AiConv $conv): Response
    {
        $this->conversationRepository->delete($conv);

        return response()->noContent();
    }

    #[Authorize('update', 'ai_conv')]
    public function storeMessage(StoreAiConvMessageRequest $request, AiConv $conv): DataResponse
    {
        $validatedData = $request->validated();

        /** @var User $user */
        $user = $request->user();
        $this->authorizeAttachments($validatedData, $user);
        $message = $this->messageHandler->create($conv, $validatedData, $user);

        return DataResponse::make($message)->withIncludePaths(['author', 'attachments'])->didCreate();
    }

    #[Authorize('update', 'ai_conv')]
    public function updateMessage(UpdateAiConvMessageRequest $request, AiConv $conv, string $messageId): DataResponse
    {
        $validatedData = $request->validated();
        $validatedData['message_id'] = $messageId;
        $validatedData['model'] ??= null;

        /** @var null|AiConvMsg $existingMessage */
        $existingMessage = $conv->messages()->where('message_id', $messageId)->first();

        if (null === $existingMessage) {
            abort(404, 'The message does not exist in this conversation.');
        }

        /** @var User $user */
        $user = $request->user();

        $this->authorizeAttachments($validatedData, $user, $existingMessage);

        /** @var AiConvMsg $message */
        $message = $this->messageHandler->update($conv, $validatedData);

        return DataResponse::make($message)->withIncludePaths(['author', 'attachments']);
    }

    #[Authorize('update', 'ai_conv')]
    public function deleteMessage(AiConv $conv, string $messageId): Response
    {
        if (!$this->messageHandler->delete($conv, ['message_id' => $messageId])) {
            abort(404, 'The message does not exist in this conversation.');
        }

        return response()->noContent();
    }

    #[Authorize('view', User::class)]
    public function storeAttachment(StoreAiConvAttachmentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $storedFile = $this->fileStorage->storeTemporary(
            file: FileReference::fromUploadedFile($request->validated('file')),
            category: StoredFileCategory::PRIVATE,
            owner: $user,
        );

        if (null === $storedFile) {
            abort(500, 'Failed to store the file.');
        }

        return response()->json([
            'uuid' => $storedFile->getUuid(),
        ], 201);
    }

    #[Authorize('view', User::class)]
    public function deleteAttachment(Request $request, string $uuid): Response
    {
        $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
        $attachment = $this->attachmentRepository->findOneByStoredFileIdentifier($identifier);
        abort_if(null === $attachment, 404);

        if ($attachment->user && !$attachment->user->is($request->user())) {
            abort(403);
        }

        if (!$attachment->attachable instanceof AiConvMsg) {
            abort(422, 'The file is not attached to a private conversation message.');
        }

        $this->attachmentRepository->delete($attachment);

        return response()->noContent();
    }

    /**
     * Rejects every attachment uuid the current user is not allowed to reference.
     *
     * Attachments are addressed by uuid alone, so this is the only thing standing between a caller and a
     * foreign upload: {@see PrivateMessageHandler} moves each listed uuid out of temp/ and attaches it to
     * the caller's message, which would hand them read access to a stranger's file and destroy the
     * uploader's pending upload. See the "Security: ownership of temporary uploads" section in
     * {@see \App\Services\Storage\AbstractFileStorage} for the full reasoning.
     *
     * @param array<string, mixed> $validatedData
     */
    private function authorizeAttachments(
        array $validatedData,
        User $user,
        ?AiConvMsg $existingMessage = null,
    ): void {
        foreach ($validatedData['content']['attachments'] ?? [] as $uuid) {
            $identifier = StoredFileIdentifier::fromCategoryAndUuid(StoredFileCategory::PRIVATE, $uuid);
            $attachment = $this->attachmentRepository->findOneByStoredFileIdentifier($identifier);

            if (null !== $attachment) {
                $belongsToExistingMessage = null !== $existingMessage
                    && $attachment->attachable instanceof AiConvMsg
                    && $attachment->attachable->is($existingMessage)
                    && $attachment->user->is($user);

                abort_unless($belongsToExistingMessage, 403, 'The attachment does not belong to this message.');

                continue;
            }

            // The temp-file cleanup deletes stale uploads together with their meta sidecar,
            // so a missing temporary file means the upload is unknown or has expired.
            $storedFile = $this->fileStorage->retrieve($identifier, temp: true);
            abort_if(
                null === $storedFile,
                422,
                'The temporary attachment is unknown or has expired. Please upload it again.',
            );
            abort_unless(
                $storedFile->isOwnedBy($user),
                403,
                'The temporary attachment does not belong to the current user.',
            );
        }
    }
}
