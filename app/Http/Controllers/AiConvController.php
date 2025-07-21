<?php

namespace App\Http\Controllers;

use App\Models\AiConv;
use App\Models\AiConvMsg;
use App\Models\User;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AiConvController extends Controller
{
    /// RETURNS CONVERSATION DATA WHICH WILL BE DYNAMICALLY LOADED ON THE PAGE
    public function loadConv($slug)
    {
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->where('user_id', $user->id)->firstOrFail();

        // Prepare the data to send back
        $data = [
            'id' => $conv->id,
            'name' => $conv->chat_name,
            'slug' => $conv->slug,
            'system_prompt'=> $conv->system_prompt,
            'messages' => $this->fetchConvMessages($conv)
        ];
        return response()->json($data);
    }



    ///CREATE NEW CONVERSATION
    public function createConv(Request $request)
    {
        $validatedData = $request->validate([
            'conv_name' => 'string|max:255',
            'system_prompt' => 'string'
        ]);

        if (!$request['conv_name']) {
            $validatedData['conv_name'] = 'New Chat';
        }

        $user = Auth::user();

        $conv = AiConv::create([
            'conv_name' => $validatedData['conv_name'],
            'user_id' => $user->id, // Associate the conversation with the user
            'slug' => Str::slug(Str::random(16)), // Create a unique slug
            'system_prompt'=> $validatedData['system_prompt'],
        ]);

        $response =[
            'success'=> true,
            'conv'=>$conv
        ];

        return response()->json($response, 201);
    }


    public function updateInfo(Request $request, $slug){
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        if ($conv->user_id != $user->id) {
            return response()->json([
                'success' => false,
                'response' => "GOTCHA: This chat doesn't belong to you",
            ]);
        }

        $validatedData = $request->validate([
            'system_prompt' => 'string'
        ]);

        $conv->update(['system_prompt' => $validatedData['system_prompt']]);

        return response()->json([
            'success' => true,
            'response' => "Info updated successfully",
        ]);
    }

    public function removeConv(Request $request, $slug){
        $user = Auth::user();
        $conv = AiConv::where('slug', $slug)->firstOrFail();

        // Check if the conv exists
        if (!$conv) {
            return response()->json(['success' => false, 'message' => 'Conv not found'], 404);
        }

        // Check if the user is an admin of the conv
        if ($conv->user_id != $user->id) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Delete related messages and members
        $conv->messages()->delete();

        $conv->delete();

        return response()->json(['success' => true, 'message' => 'Conv deleted successfully']);
    }

    public function getUserConvs(Request $request)
    {
        // Assuming the user is authenticated
        $user = auth()->user();

        // Fetch all conversations related to the user
        $convs = $user->conversations()->with('messages')->get();

        return response()->json($convs);
    }


    /// get all messages in the conv
    /// 1. find conv in DB
    /// 2. create message array
    /// 3. return message array
    public function fetchConvMessages(AiConv $conv){

        $messages = $conv->messages;
        $messagesData = array();
        foreach ($messages as $message){

            //if AI is the author, then username and name are the same.
            //if User has created the message then fetch the name from model.
            $user =  $message->user;
            $msgData = [
                'id' => $message->id,
                'conv_id' => $message->conv_id,
                'message_role' => $message->message_role,
                'message_id' => $message->message_id,
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null,
                ],
                'model' => $message->model,
                'iv' => $message->iv,
                'tag' => $message->tag,
                'content' => $message->content,
                'completion' => $message->completion,
                'created_at' => $message->created_at->format('Y-m-d+H:i'),
                'updated_at' => $message->updated_at->format('Y-m-d+H:i'),
            ];

            array_push($messagesData, $msgData);
        }
        return $messagesData;

    }


    /// 1. find the conv on DB
    /// 2. check the membership validation
    /// 3. assign an id to the message
    /// 4. create message object
    /// 5. qeue message for broadcasting
    /// 6. send response to the sender
    public function sendMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'isAi' => 'required|boolean',
            'threadID' => 'required|integer|min:0',
            'content' => 'required|string',
            'iv' => 'required|string',
            'tag' => 'required|string',
            'model' => 'string',
            'completion' => 'required|boolean',
        ]);


        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        $user = $validatedData['isAi'] ? User::find(1) : Auth::user();
        $messageRole = $validatedData['isAi'] ? 'assistant' : 'user';

        $nextMessageId = $this->generateMessageID($conv, $validatedData['threadID']);
        $message = AiConvMsg::create([
            'conv_id' => $conv->id,
            'user_id' => $user->id,
            'model' => $validatedData['isAi'] ? $validatedData['model'] : null,

            'message_role' => $messageRole,
            'message_id' => $nextMessageId,
            'iv' => $validatedData['iv'],
            'tag' => $validatedData['tag'],
            'content' => $validatedData['content'],
            'completion' => $validatedData['completion'],
        ]);

        // add author data + creation and update dates to response data.
        $messageData = $message->toArray();
        $messageData['author'] = [
            'username' => $user->username,
            'name' => $user->name,
            'avatar_url' => $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null,
        ];
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');


        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message created and boradcasted.",
        ]);
    }



    public function updateMessage(Request $request, $slug) {

        $validatedData = $request->validate([
            'message_id' => 'required|string',
            'content' => 'required|string',
            'iv' => 'required|string',
            'tag' => 'required|string',
            'model' => 'nullable|string',
            'completion' => 'required|boolean',
        ]);

        $conv = AiConv::where('slug', $slug)->firstOrFail();
        if ($conv->user_id !== Auth::id()) {
            return response()->json(['error' => 'Access denied'], 403);
        }

        //find the target message
        $message = $conv->messages->where('message_id', $validatedData['message_id'])->first();

        $message->update([
            'content' => $validatedData['content'],
            'iv' => $validatedData['iv'],
            'tag' => $validatedData['tag'],
            'model' => $validatedData['model'],
            'completion' => $validatedData['completion']
        ]);

        $messageData = $message->toArray();
        $messageData['created_at'] = $message->created_at->format('Y-m-d+H:i');
        $messageData['updated_at'] = $message->updated_at->format('Y-m-d+H:i');

        return response()->json([
            'success' => true,
            'messageData' => $messageData,
            'response' => "Message updated.",
        ]);

    }



    private function generateMessageID(AiConv $conv, int $threadID) {
        $decimalPadding = 3; // Decide how much padding you need. 3 could pad up to 999.

        if ($threadID == 0) {
            // Fetch all messages with whole number IDs (e.g., "0.0", "1.0", etc.)
            $allMessages = $conv->messages()
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
            $allMessages = $conv->messages()
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
