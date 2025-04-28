<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\User;
use App\Models\Member;
use App\Models\Message;

use App\Jobs\SendMessage;

use App\Http\Controllers\InvitationController;
use App\Http\Controllers\ImageController;
use Illuminate\Support\Facades\Storage;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;


class RoomController extends Controller
{

    /// Returns requested Room Data + Messages
    public function loadRoom($slug)
    {
        $room = Room::where('slug', $slug)->firstOrFail();
    
        // Optionally, check if the authenticated user is a member of the room
        if (!auth()->check() || !$room->isMember(auth()->id())) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        // Prepare the data to send back

        $roomIcon = ($room->room_icon !== '' && $room->room_icon !== null) 
        ? Storage::disk('public')->url('room_avatars/' . $room->room_icon) 
        : null;


        $membership = $room->members()->where('user_id', Auth::user()->id)->first();
        $membership->updateLastRead();

        $role = $membership->role;

        $data = [
            'id' => $room->id,
            'name' => $room->room_name,
            'room_icon' => $roomIcon,
            'slug' => $room->slug,
            'system_prompt' => $room->system_prompt,
            'room_description' => $room->room_description,
            'role' => $role,

            'members' => $room->members->map(function ($member) {
                return [
                    'user_id' => $member->user->id,
                    'name' => $member->user->name,
                    'username' => $member->user->username,
                    'role' => $member->role,
                    'employeetype' => $member->user->employeetype,
                    'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
                ];
            }),
        
            'messagesData' => $this->fetchRoomMessages($room)
        ];
    
        return response()->json($data);
    }
    


    /// Create new room template upon user request
    public function createRoom(Request $request)
    {
        // Validate the incoming request data
        $validatedData = $request->validate([
            'room_name' => 'required|string|max:255',
        ]);

        
        // Create the room with name and description
        $room = Room::create([
            'room_name' => $validatedData['room_name'],
        ]);


        $user = Auth::user();

        //INVITE MEMEBERS

        // Add AI as assistant
        $room->addMember(1, Member::ROLE_ASSISTANT);
        // Add the creator as admin
        $room->addMember($user->id, Member::ROLE_ADMIN);

        $data =[
            'success' => true,
            'roomData' => $room,
            // 'inviteesKeys' => $inviteesKeys,
        ];

        return response()->json($data, 201);
    }


    public function removeRoom($slug){
        $user = Auth::user();
        $room = Room::where('slug', $slug)->firstOrFail();
        
        
        // Check if the room exists
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }
    
        // Delete related messages and members
        $room->messages()->delete();
        $room->members()->delete();
    
        // Delete the room itself
        $room->delete();
    
        return response()->json(['success' => true, 'message' => 'Room deleted successfully']);
    }

    /// Update room info
    /// This is also executed after completing room creation.
    public function updateInfo(Request $request, $slug)
    {
        $user = Auth::user();
        $room = Room::where('slug', $slug)->firstOrFail();

        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();

        if(!$member){
            return response()->json(['error' => 'Access denied'], 403);
        }

        $validatedData = $request->validate([
            'img' => 'string',
            'system_prompt' => 'string',
            'description' => 'string',
            'name' => 'string'
        ]);

        if(!empty($validatedData['img'])){
            $imageController = new ImageController();
            $response = $imageController->storeImage($validatedData['img'], 'room_avatars');
            $response = $response->original;

            if ($response && $response['success']) {
                $room->update(['room_icon' => $response['fileName']]);
            } else {
                return response()->json([
                    'success' => false,
                    'response' => 'Image upload failed: ' . $response['error'] ?? 'Unknown error'
                ]);
            }
        }
        // else{
        //     $room->update(['room_icon' => '']);
        // }

        if(!empty($validatedData['system_prompt'])){
            $room->update(['system_prompt' => $validatedData['system_prompt']]);
        }
        if(!empty($validatedData['description'])){
            $room->update(['room_description' => $validatedData['description']]);
        }
        if(!empty($validatedData['name'])){
            $room->update(['room_name' => $validatedData['name']]);
        }

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }


    
    /// ADD MEMBER TO THE ROOM
    public function addMember(Request $request)
    {
        $requester = Auth::user();

        $slug = $request->slug;

        $room = Room::where('slug', $slug)->firstOrFail();

        $user = User::where('username', $request->invitee)->firstOrFail();
        $roomID = $room->id;

        if($room->isMember(auth()->id()) && $room->hasRole(auth()->id(), Member::ROLE_ADMIN ) ){

            $room->addMember($user->id, $request->role);
            return response()->json($room->members);
        }

        return response()->json('failed to add member');
    }



    public function leaveRoom(Request $request, $slug){

        $room = Room::where('slug', $slug)->firstOrFail();
        // Check if room exists
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $user = Auth::user();
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'User is not a member of the room'], 404);
        }
        $response = $this->removeRoomMember($member, $room);
        return $response;
    }


    public function removeMember(Request $request, $slug){
        $validatedData = $request->validate([
            'username' => 'string|max:16',
        ]);

        $room = Room::where('slug', $slug)->firstOrFail();
        // Check if room exists
        if (!$room) {
            return response()->json(['success' => false, 'message' => 'Room not found'], 404);
        }

        $requester = Auth::user();

        $username = $request->username;
        $user = User::where('username', $username)->firstOrFail();
        // Check if the user is a member of the room
        $member = $room->members()->where('user_id', $user->id)->firstOrFail();
        if (!$member) {
            return response()->json(['success' => false, 'message' => 'User is not a member of the room'], 404);
        }

        if($member->user_id === '1'){
            return response()->json(['success' => false, 'message' => "You can't remove the AI agent from a room!"]);
        }

        $response = $this->removeRoomMember($member, $room);
        return $response;

    }


    public function removeRoomMember(Member $member, Room $room)
    {
        // Remove the member from the room
        $room->removeMember($member->user_id);
    
        //Check if All the members have left the room.
        if ($room->members()->count() === 1) {
            $this->removeRoom($room->slug);
        }

        return response()->json(['success' => true, 'message' => 'Member removed from the room']);
    }


    /// GET ALL ROOMS THAT THE USER IS IA MEMBER IN
    public function getUserRooms(Request $request)
    {
        // Assuming the user is authenticated
        $user = auth()->user();

        $roomsList = [];
        // Fetch all rooms where the user is a member
        $rooms = $user->rooms;
        foreach($rooms as $room){
            //findout the membership
            $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
            //check if this memeber has unread messages
            $roomItem = [
                'room' => $room,
                'hasUnreadMessages'=> $room->hasUnreadMessagesFor($member)
            ];
            array_push($roomsList, $roomItem);
        }
        return response()->json($roomsList);
    }


    /// Format and return messages data of the room 
    public function fetchRoomMessages(Room $room){

        $messages = $room->messages;

        $messagesData = array();
        foreach ($messages as $message){
            $member = Member::find($message->member_id);
            $requestMember = $room->membersAll()->where('user_id', Auth::id())->firstOrFail();

            $readStat = $message->isReadBy($requestMember);

            $msgData = [
                'id' => $message->id,
                'room_id' => $message->room_id,
                'member_id' => $member->id,
                'member_name' => $member->user->name,
                'message_role' => $message->message_role,
                'message_id' => $message->message_id,
                'read_status'=> $readStat,

                'author' => [
                    'username' => $member->user->username,
                    'name' => $member->user->name,
                    'isRemoved' => $member->isRemoved,
                    'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
                ],
                'model' => $message->model,

                'content' => $message->content,
                'iv' => $message->iv,
                'tag' => $message->tag,
                'created_at' => $message->created_at->format('Y-m-d+H:i'),
                'updated_at' => $message->updated_at->format('Y-m-d+H:i'),
            ]; 

            array_push($messagesData, $msgData);
        }
        return $messagesData;
    }




    /// sendMessage()
    /// 1. find the room on DB
    /// 2. check the membership validation
    /// 3. assign an id to the message
    /// 4. create message object
    /// 5. qeue message for broadcasting
    /// 6. send response to the sender
    public function sendMessage(Request $request, $slug) {
        
        $validatedData = $request->validate([
            'content' => 'required|string',
            'iv' => 'required|string',
            'tag' => 'required|string',
            'threadID' => 'required|int',
        ]);

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $messageRole = 'user';

        $nextMessageId = $this->generateMessageID($room, $validatedData['threadID']);

        $message = Message::create([
            'room_id' => $room->id,
            'member_id' => $member->id,
            'user_id' => Auth::id(),
            'message_id' => $nextMessageId,
            'message_role' => $messageRole,
            'iv' => $validatedData['iv'],
            'tag' => $validatedData['tag'],
            'content' => $validatedData['content'],
        ]);
        $message->addReadSignature($member);


        SendMessage::dispatch($message, false)->onQueue('message_broadcast');

        if(!$room || !$member){
            return response()->json([
                'success' => false,
                'response' => "Failed to send message",
            ]);
        }

        $messageData = [
            'id' => $message->id,
            'room_id' => $message->room_id,
            'member_id' => $member->id,
            'message_role' => $messageRole,
            'message_id' => $message->message_id,
            'member_left' => false,

            'author' => [
                'username' => $member->user->username,
                'name' => $member->user->name,
                'avatar_url' => $member->user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $member->user->avatar_id) : null,
            ],
            
            'content' => $message->content,
            'iv' => $message->iv,
            'tag' => $message->tag,

            'created_at' => $message->created_at->format('Y-m-d+H:i'),
            'updated_at' => $message->updated_at->format('Y-m-d+H:i'),
        ]; 


        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message created and boradcasted.",
        ]);
    }



    public function updateMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'iv' => 'required|string',
            'tag' => 'required|string',
            'content' => 'required|string|max:10000',
            'message_id' => 'required|string',
        ]);

        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();


        $message = $room->messages->where('message_id', $validatedData['message_id'])->first();

        $message->update([
            'content' => $validatedData['content'],
            'iv' => $validatedData['iv'],
            'tag' => $validatedData['tag']
        ]);

        SendMessage::dispatch($message, true)->onQueue('message_broadcast');

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

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
        $room = Room::where('slug', $slug)->firstOrFail();
        $member = $room->members()->where('user_id', Auth::id())->firstOrFail();
        $message = $room->messages->where('message_id', $validatedData['message_id'])->first();

        $message->addReadSignature($member);

        return response()->json([
                'success' => true,
            ]);
    }

    /// Generates a message ID based on the previous messages of the thread.
    public function generateMessageID(Room $room, int $threadID) {
        $decimalPadding = 3; // Decide how much padding you need. 3 could pad up to 999.
        
        if ($threadID == 0) {
            // Fetch all messages with whole number IDs (e.g., "0.0", "1.0", etc.)
            $allMessages = $room->messages()
                                ->get()
                                ->filter(function ($message) {
                                    return floor(floatval($message->message_id)) == floatval($message->message_id);
                                });
    
            if ($allMessages->isNotEmpty()) {
                // Find the message with the highest whole number
                $lastMessage = $allMessages->sortByDesc(function ($message) {
                    return intval($message->message_id);
                })->first();
    
                // Increment the whole number part
                $newWholeNumber = intval($lastMessage->message_id) + 1;
                $newMessageId = $newWholeNumber . '.000'; // Start with 3 zeros
            } else {
                // If no messages exist, start from 1.000
                $newMessageId = '1.000';
            }
        } else {
            // Fetch all messages that belong to the specified threadID
            $allMessages = $room->messages()
                                ->where('message_id', 'like', "$threadID.%")
                                ->get();
    
            if ($allMessages->isNotEmpty()) {
                // Find the message with the highest decimal part
                $lastMessage = $allMessages->sortByDesc(function ($message) {
                    return floatval($message->message_id);
                })->first();
    
                // Increment the decimal part
                $parts = explode('.', $lastMessage->message_id);
                $newDecimal = intval($parts[1]) + 1;
                $newMessageId = $parts[0] . '.' . str_pad($newDecimal, $decimalPadding, '0', STR_PAD_LEFT);
            } else {
                // If no sub-messages exist, start from threadID.001
                $newMessageId = $threadID . '.001';
            }
        }
    
        return $newMessageId;
    }

}