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
use App\Services\Storage\TemporaryUploadOwnership;
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
        protected readonly TemporaryUploadOwnership $temporaryUploadOwnership,
    ) {
    }

    public function creating(ResourceRequest $request, ResourceQuery $query): DataResponse
    {
        $validated = $request->validated();
        $conversation = $this->conversationRepository->create(
            $validated['name'] ?? null,
            $validated['system_prompt'] ?? null,
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
        $temporaryAttachments = $this->authorizeAttachments($validatedData, $user);
        $message = $this->messageHandler->create($conv, $validatedData, $user);
        $this->forgetTemporaryAttachments($temporaryAttachments);

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

        $temporaryAttachments = $this->authorizeAttachments($validatedData, $user, $existingMessage);

        /** @var AiConvMsg $message */
        $message = $this->messageHandler->update($conv, $validatedData);

        $this->forgetTemporaryAttachments($temporaryAttachments);

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
    public function storeAttachment(StoreAiConvAttachmentRequest $request, FileStorageService $fileStorage): JsonResponse
    {
        $storedFile = $fileStorage->storeTemporary(
            file: FileReference::fromUploadedFile($request->validated('file')),
            category: StoredFileCategory::PRIVATE,
        );

        if (null === $storedFile) {
            abort(500, 'Failed to store the file.');
        }

        /** @var User $user */
        $user = $request->user();

        if (!$this->temporaryUploadOwnership->register($storedFile->getIdentifier(), $user)) {
            $fileStorage->delete($storedFile->getIdentifier(), true);
            abort(500, 'Failed to register the temporary file.');
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
     * @param array<string, mixed> $validatedData
     *
     * @return list<StoredFileIdentifier>
     */
    private function authorizeAttachments(
        array $validatedData,
        User $user,
        ?AiConvMsg $existingMessage = null,
    ): array {
        $temporaryAttachments = [];

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

            abort_unless(
                $this->temporaryUploadOwnership->isRegistered($identifier),
                422,
                'The temporary attachment is unknown or has expired. Please upload it again.',
            );
            abort_unless(
                $this->temporaryUploadOwnership->isOwnedBy($identifier, $user),
                403,
                'The temporary attachment does not belong to the current user.',
            );
            $temporaryAttachments[] = $identifier;
        }

        return $temporaryAttachments;
    }

    /**
     * @param list<StoredFileIdentifier> $identifiers
     */
    private function forgetTemporaryAttachments(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            $this->temporaryUploadOwnership->forget($identifier);
        }
    }
}
