<?php

namespace App\Http\Controllers;

use App\Http\Resources\Legacy\AiConvMsgResource;
use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\Attachment;
use App\Services\Chat\AiConv\AiConvService;
use App\Services\Chat\Attachment\Db\AttachmentDb;
use App\Services\Chat\Message\Handlers\PrivateMessageHandler;
use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Storage\FileStorageService;
use App\Services\Storage\Value\FileReference;
use App\Services\Storage\Value\StoredFileCategory;
use App\Services\Storage\Value\StoredFileIdentifier;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class AiConvController extends Controller
{
    public function __construct(
        protected readonly AttachmentDb            $attachmentService,
        protected readonly AiConvService           $aiConvService,
        protected readonly MessageContentValidator $contentValidator,
        protected readonly PrivateMessageHandler   $messageHandler
    )
    {
    }

    ///CREATE NEW CONVERSATION
    public function create(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'conv_name' => 'nullable|string|max:255',
            'system_prompt' => 'nullable|string'
        ]);

        $conv = $this->aiConvService->create($validatedData);

        return response()->json([
            'success' => true,
            'conv' => $conv,
        ], 201);
    }


    /// RETURNS CONVERSATION DATA WHICH WILL BE DYNAMICALLY LOADED ON THE PAGE
    public function load($slug): JsonResponse
    {
        $convData = $this->aiConvService->load($slug);
        return response()->json([
            'success' => true,
            'data' => $convData,
        ]);
    }


    public function update(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'system_prompt' => 'string'
        ]);
        $this->aiConvService->update($validatedData, $slug);

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }

    public function delete($slug): JsonResponse
    {
        $this->aiConvService->delete($slug);
        return response()->json([
            'success' => true,
            'message' => 'Conv deleted successfully'
        ]);
    }


    public function sendMessage(Request $request, $slug, MessageContentValidator $contentValidator): JsonResponse
    {

        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'threadId' => 'required|integer|min:0',
            'content' => 'required|array',
            'metadata' => 'nullable|array',

            'model' => 'string',
            'completion' => 'required|boolean',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);

        // CREATE MESSAGE
        $conv = AiConv::where('slug', $slug)->firstOrFail();
        $message = $this->messageHandler->create($conv, $validatedData, $request->user());

        return response()->json([
            'success' => true,
            'messageData' => $message->toResource(AiConvMsgResource::class)->resolve()
        ]);
    }


    public function updateMessage(Request $request, $slug, MessageContentValidator $contentValidator): JsonResponse
    {

        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'content' => 'required|array',
            'metadata' => 'nullable|array',
            'model' => 'nullable|string',
            'completion' => 'required|boolean',
            'message_id' => 'required|string',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);
        $conv = AiConv::where('slug', $slug)->firstOrFail();
        $message = $this->messageHandler->update($conv, $validatedData);
        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return response()->json([
            'success' => true,
            'messageData' => $messageData,
        ]);
    }

    public function deleteMessage(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate([
            "message_id" => 'required|string|size:5'
        ]);

        $conv = AiConv::where('slug', $slug)->first();
        $deleted = $this->messageHandler->delete($conv, $validatedData);

        return response()->json([
            'success' => true,
        ]);


    }


    /// ATTACHMENT FUNCTIONS
    ///

    public function storeAttachment(Request $request, FileStorageService $fileStorage): JsonResponse
    {
        $validateData = $request->validate([
            'file' => 'required|file|max:' . ($fileStorage->getMaxFileSize() / 1024)
        ]);

        $storedFile = $fileStorage->storeTemporary(
            file: FileReference::fromUploadedFile($validateData['file']),
            category: StoredFileCategory::PRIVATE,
        );

        if ($storedFile === null) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to store file'
            ], 500);
        }

        return response()->json([
            'success' => true,
            'uuid' => $storedFile->getUuid(),
        ]);
    }

    /**
     * @throws Exception
     */
    public function getAttachmentUrl(string $uuid, FileStorageService $fileStorage): JsonResponse
    {
        $attachment = Attachment::where('uuid', $uuid)->firstOrFail();
        if ($attachment->user->isNot(Auth::user())) {
            throw new AuthorizationException();
        }
        $url = $fileStorage->retrieve(StoredFileIdentifier::fromAttachment($attachment))?->getUrl();
        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }

    public function deleteAttachment(Request $request): JsonResponse
    {
        $validateData = $request->validate([
            'fileId' => 'required|string',
        ]);

        try {
            $attachment = Attachment::where('uuid', $validateData['fileId'])->firstOrFail();

            if ($attachment->user && !$attachment->user->is(Auth::user())) {
                throw new AuthorizationException();
            }

            if (!$attachment->attachable instanceof AiConvMsg) {
                return response()->json([
                    'success' => false,
                    'err' => 'File Id does not match the properties!'
                ], 500);
            }

            // @todo: I assume with the AttachmentDeleting event, storage system will automatically remove the files.
            // If correct, AttachmentService can be removed from the construct.
            $result = $attachment->delete();
//            $result = $this->attachmentService->delete($attachment);
            return response()->json([
                "success" => $result
            ]);
        } catch (Exception $e) {
            Log::error($e);
            throw $e;

        }
    }
}
