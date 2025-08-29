<?php

namespace App\Http\Controllers;

use App\Models\Attachment;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

use App\Services\Chat\Room\RoomService;

use App\Services\Chat\Message\MessageContentValidator;
use App\Services\Chat\Attachment\AttachmentService;

use Exception;
use Illuminate\Auth\Access\AuthorizationException;

class RoomController extends Controller
{
    protected $roomService;

    public function __construct(RoomService $roomService)
    {
        $this->roomService = $roomService;
    }

    // SECTION: ROOM CONTROLS
    public function create(Request $request)
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
    public function load($slug)
    {
        $data = $this->roomService->load($slug);
        return response()->json($data);
    }



    public function update(Request $request, $slug)
    {
        $validatedData = $request->validate([
            'img' => 'string',
            'system_prompt' => 'string',
            'description' => 'string',
            'name' => 'string'
        ]);
        $this->roomService->update($validatedData, $slug);

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }


    public function delete($slug){
        $this->roomService->delete($slug);
        return response()->json([
            'success' => true,
            'message' => 'Room deleted successfully'
        ]);
    }


    // SECTION: MEMBER
    public function addMember(Request $request, $slug)
    {
        $validatedData = $request->validate([
            'invitee' => 'string',
            'role'=>'string'
        ]);
        $this->roomService->add($slug, $validatedData);
        return response()->json('failed to add member');
    }


    public function leaveRoom($slug){
        $success = $this->roomService->leave($slug);

        return response()->json([
            'success' => $success
        ]);
    }


    public function kickMember(Request $request, $slug){
        $validatedData = $request->validate([
            'username' => 'string|max:16',
        ]);
        $success = $this->roomService->kick($slug, $validatedData['username']);
        return response()->json([
            'success' => $success
        ]);
    }


    public function searchUser(Request $request)
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
    public function sendMessage(Request $request, $slug, MessageContentValidator $contentValidator) {

        $validatedData = $request->validate([
            'content' => 'required|array',
            'threadID' => 'required|integer',
        ]);
        $validatedData['content'] = $contentValidator->validate($validatedData['content']);

        $messageData = $this->roomService->sendMessage($validatedData, $slug);

        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message created and boradcasted.",
        ]);
    }



    public function updateMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'content' => 'required|array',
            'message_id' => 'required|string',
        ]);
        $messageData = $this->roomService->update($validatedData, $slug);
        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message updated.",
        ]);

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
    public function storeAttachment(Request $request, AttachmentService $attachmentService) {
        $validateData = $request->validate([
            'file' => 'required|file|max:20480'
        ]);
        $result = $attachmentService->store($validateData['file'], 'group');
        return response()->json($result);

    }

    public function getAttachmentUrl(string $uuid, AttachmentService $attachmentService) {

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


    public function deleteAttachment(Request $request, AttachmentService $attachmentService) {
        $validateData = $request->validate([
            'fileId' => 'required|string',
        ]);

        try{
            $attachment = Attachment::where('uuid', $validateData['fileId'])->firstOrFail();

            if ($attachment->user && !$attachment->user->is(Auth::user())) {
                throw new AuthorizationException();
            }

            if (!$attachment->attachable instanceof Message) {
                return response()->json([
                    'success'=> false,
                    'error'=> 'File Id does not match the properties!'
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
