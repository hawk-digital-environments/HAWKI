<?php

namespace App\Http\Controllers;

use App\Models\Attachment;

use App\Models\Message;
use App\Services\Storage\FileStorageService;
use Dotenv\Exception\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\Chat\Room\RoomService;

use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Chat\Attachment\AttachmentService;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;

use Illuminate\Http\JsonResponse;

class RoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    // SECTION: ROOM CONTROLS
    public function create(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'room_name' => 'required|string|max:255',
        ]);
        $data = $this->roomService->create($validatedData);
        return response()->json([
            "success" => true,
            "roomData" => $data
        ], 201);
    }

    /// Returns requested Room Data + Messages
    public function load($slug): JsonResponse
    {
        $data = $this->roomService->load($slug);
        return response()->json($data);
    }



    public function update(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'system_prompt' => 'nullable|string',
            'description' => 'nullable|string',
            'name' => 'nullable|string',
            'image' => 'nullable|file',
        ]);
        $this->roomService->update($validatedData, $slug);

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }

    function uploadAvatar(Request $request, $slug = null): JsonResponse
    {
        $validatedData = $request->validate([
            'image' => 'required|file|max:20480'
        ]);

        $response = $this->roomService->assignAvatar($validatedData['image'],
                                        $slug);

        return response()->json([
            "success" => true,
            "url" => $response['url'],
            "uuid"=> $response['uuid'],
        ]);
    }

    public function delete($slug): JsonResponse{
        $this->roomService->delete($slug);
        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully'
        ]);
    }


    // SECTION: MEMBER
    public function addMember(Request $request, $slug): JsonResponse
    {
        $validatedData = $request->validate([
            'invitee' => 'string',
            'role'=>'string'
        ]);
        $members = $this->roomService->add($slug, $validatedData);
        return response()->json($members);
    }


    public function leaveRoom($slug): JsonResponse{
        $success = $this->roomService->leave($slug);

        return response()->json([
            'success' => $success
        ]);
    }


    public function kickMember(Request $request, $slug): JsonResponse{
        $validatedData = $request->validate([
            'username' => 'string|max:16',
        ]);
        $success = $this->roomService->kick($slug, $validatedData['username']);
        return response()->json([
            'success' => $success
        ]);
    }


    public function searchUser(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'query' => 'string'
        ]);
        $results = $this->roomService->searchUser($validatedData['query']);

        if ($results->count() > 0) {
            return response()->json([
                'success' => true,
                'users' => $results,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'No users found',
            ]);
        }
    }



    // SECTION: MESSAGE
    public function sendMessage(Request $request, $slug, MessageContentValidator $contentValidator): JsonResponse {

        $validatedData = $request->validate([
            'content' => 'required|array',
            'threadId' => 'required|integer',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);

        $messageData = $this->roomService->sendMessage($validatedData, $slug);

        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message created and boradcasted.",
        ]);
    }



    public function updateMessage(Request $request, $slug): JsonResponse {

        $validatedData = $request->validate([
            'content' => 'required|array',
            'message_id' => 'required|string',
        ]);
        $messageData = $this->roomService->updateMessage($validatedData, $slug);
        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message updated.",
        ]);

    }


    public function retrieveMessage($slug, $message_id): JsonResponse{
        if (!is_string($slug) || !is_string($message_id)) {
            throw new ValidationException();
        }
        $messageData = $this->roomService->retrieveMessage($message_id, $slug);
        return response()->json($messageData);
    }


    public function markAsRead(Request $request, $slug){
        $validatedData = $request->validate([
            'message_id' => 'required|string',
        ]);
        $this->roomService->markAsRead($validatedData, $slug);
        return response()->json([
            'success' => true,
        ]);
    }


    // SECTION: ATTACHMENTS
    public function storeAttachment(Request $request, AttachmentService $attachmentService): JsonResponse {
        $validateData = $request->validate([
            'file' => 'required|file|max:20480'
        ]);
        $result = $attachmentService->store($validateData['file'], 'group');
        return response()->json($result);

    }

    public function getAttachmentUrl(string $uuid, AttachmentService $attachmentService): JsonResponse {

        try {
            $attachment = Attachment::where('uuid', $uuid)->firstOrFail();

            // If the requesting User is NOT a member of this group RETURN 403
            if(!$attachment->attachable->room->isMember(Auth::id())){
                throw new AuthorizationException();
            }

            $url = $attachmentService->getFileUrl($attachment, null);
        }
        catch (Exception $e) {
            throw $e;
        }

        return response()->json([
            'success' => true,
            'url' => $url
        ]);
    }
    public function downloadAttachment(string $uuid, string $path)
    {
        try {
            $attachment = Attachment::where('uuid', $uuid)->firstOrFail();
            if(!$attachment->attachable->room->isMember(Auth::id())){
                throw new AuthorizationException();
            }

            $storageService = app(FileStorageService::class);
            $stream = $storageService->streamFromSignedPath($path); // returns a resource

            return response()->streamDownload(function () use ($stream)
            {
                fpassthru($stream); // send stream directly to browser
            },
                $attachment->filename,
                [
                    'Content-Type' => $attachment->mime,
                ]
            );
        } catch (\Illuminate\Contracts\Filesystem\FileNotFoundException $e) {
            abort(404, 'File not found');
        }
    }

    /**
     * @throws Exception
     */
    public function deleteAttachment(Request $request, AttachmentService $attachmentService): JsonResponse {
        Log::debug('attachment Delete');

        $validateData = $request->validate([
            'fileId' => 'required|string',
        ]);
        try{
            $attachment = Attachment::where('uuid', $validateData['fileId'])->firstOrFail();

            $room = $attachment->attachable->room;
            if(!$room->isMember(Auth::id())){
                throw new AuthorizationException();
            }
            $membership = $room->members->where('user_id', Auth::id())->firstOrFail();
            if(!$membership->hasRole('admin') || !$attachment->user->is(Auth::user())) {
                throw new AuthorizationException();
            }
            if (!$attachment->attachable instanceof Message) {
                return response()->json([
                    'success'=> false,
                    'error'=> 'File Category does not match the properties!'
                ], 500);
            }

            $result = $attachmentService->delete($attachment);
            return response()->json([
                "success" => $result
            ]);
        }
        catch(Exception $e) {
            Log::error($e);
            throw $e;
        }
    }

}
