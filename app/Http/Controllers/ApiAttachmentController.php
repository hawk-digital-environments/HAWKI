<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Services\Chat\Attachment\AttachmentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class ApiAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService
    ) {}

    /**
     * Upload a file for use in a subsequent API AI request.
     * Returns a UUID that can be included in `content.attachments` of an AI request payload.
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => 'required|file|max:20480|mimes:jpeg,jpg,png,gif,webp,bmp,tiff,pdf,doc,docx',
        ]);

        $result = $this->attachmentService->storeForApi($validated['file']);

        if (!$result) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload file.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'uuid'    => $result['uuid'],
            'name'    => $result['name'],
            'mime'    => $result['mime'],
            'type'    => $result['type'],
        ]);
    }

    /**
     * Delete a previously uploaded API attachment.
     */
    public function delete(string $uuid): JsonResponse
    {
        try {
            $attachment = Attachment::where('uuid', $uuid)->firstOrFail();

            if ((int) $attachment->user_id !== (int) Auth::id()) {
                throw new AuthorizationException('You are not allowed to delete this attachment.');
            }

            $this->attachmentService->delete($attachment);

            return response()->json(['success' => true]);
        } catch (AuthorizationException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 403);
        } catch (Exception $e) {
            Log::error('Failed to delete API attachment: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Failed to delete attachment.'], 500);
        }
    }
}
