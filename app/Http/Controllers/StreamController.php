<?php

namespace App\Http\Controllers;

use App\Http\Controllers\RoomController;

use App\Models\User;
use App\Models\Room;
use App\Models\Message;
use App\Models\Member;

use App\Services\AI\AiPayloadFormatterService;
use App\Services\AI\AiResponseFormatterService;
use App\Services\AI\ModelUtilityService;
use App\Services\AI\ModelConnectionService;
use App\Services\AI\UsageAnalyzerService;

use App\Jobs\SendMessage;
use App\Events\RoomMessageEvent;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{

    protected $aiFormatter;

    public function __construct(AiPayloadFormatterService $payloadFormatter, 
                                AiResponseFormatterService $responseFormatter, 
                                ModelUtilityService $utilities,
                                ModelConnectionService $modelConnection,
                                UsageAnalyzerService $usageAnalyzer){
        $this->payloadFormatter = $payloadFormatter;
        $this->responseFormatter = $responseFormatter;
        $this->utilities = $utilities;
        $this->modelConnection = $modelConnection;
        $this->usageAnalyzer = $usageAnalyzer;
    }


    public function handleExternalRequest(Request $request)
    {
        // Find out user model
        $user = Auth::user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            // Validate request data
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.stream' => 'required|boolean',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            // Return detailed validation error response
            return response()->json([
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        try {
            // Format the payload for internal use
            $formattedPayload = $this->payloadFormatter->formatPayload($validatedData['payload']);
        } catch (\Exception $e) {
            // Handle formatting errors, e.g., unsupported provider/model
            return response()->json([
                'message' => 'Payload Formatting Error',
                'error' => $e->getMessage()
            ], 400);
        }

        //find the target model from config.
        $models = $this->utilities->getModels()['models'];

        // search and find defined model based on the requested id.
        $targetID = $formattedPayload['model'];
        $filteredModels = array_filter($models, function($model) use ($targetID) {
            return $model['id'] === $targetID;
        });
        $model = current($filteredModels);

        if($formattedPayload['stream'] && $model['streamable']){
            $formattedPayload['stream_options'] = [
                "include_usage"=> true,
            ];
            $this->createStream($formattedPayload);
        }
        else{
            $data = $this->createRequest($formattedPayload);
            return response()->json($data);
        }
    }
    



    public function handleAiConnectionRequest(Request $request)
    {
        $validatedData = $request->validate([
            'payload.model' => 'required|string',
            'payload.stream' => 'required|boolean',
            'payload.messages' => 'required|array',
            'payload.messages.*.role' => 'required|string',
            'payload.messages.*.content' => 'required|array',
            'payload.messages.*.content.text' => 'required|string',

            'broadcast' => 'required|boolean',
            'isUpdate' => 'nullable|boolean',
            'messageId' => 'nullable|string',
            'threadIndex' => 'nullable|int', 
            'slug' => 'nullable|string',
            'key' => 'nullable|string',
        ]);

        $formattedPayload = $this->payloadFormatter->formatPayload($validatedData['payload']);

        if ($validatedData['broadcast']) {
            $this->handleGroupChatRequest($validatedData, $formattedPayload);
        }
        else{
            //find the target model from config.
            $models = $this->utilities->getModels()['models'];

            // search and find defined model based on the requested id.
            $targetID = $formattedPayload['model'];
            $filteredModels = array_filter($models, function($model) use ($targetID) {
                return $model['id'] === $targetID;
            });
            $model = current($filteredModels);

            if($formattedPayload['stream'] && $model['streamable']){
                $formattedPayload['stream_options'] = [
                    "include_usage"=> true,
                ];
                $this->createStream($formattedPayload);
            }
            else{
                $data = $this->createRequest($formattedPayload);
                return response()->json($data);

            }
        }
    }
    
    private function handleGroupChatRequest($data, $formattedPayload){

        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();
    
        // Broadcast initial generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => true, // Set to true while still generating
                'model' => $formattedPayload['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));
    

        // Send a full request to the AI model and get the response
        $provider = $this->utilities->getProvider($formattedPayload['model']);
        if($provider['id'] === 'google'){
            $response = $this->modelConnection->requestToGoogle($formattedPayload);
            [$content, $usage] = $this->responseFormatter->formatGoogleResponse($response);
        }
        else{
            $response = $this->modelConnection->requestToAiModel($formattedPayload);
            [$content, $usage] = $this->responseFormatter->formatDefaultResponse($response);
        }
        $this->usageAnalyzer->submitUsageRecord($usage, 'group', $formattedPayload['model'], $room->id);


        $roomController = new RoomController();
        $member = $room->members()->where('user_id', 1)->firstOrFail();

        $cryptoController = new EncryptionController();
        $encKey = base64_decode($data['key']);
        $encryptiedData = $cryptoController->encryptWithSymKey($encKey, $content, false);

        if ($isUpdate) {
            $message = $room->messages->where('message_id', $data['messageId'])->first();
            $message->update([
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
            ]);
        } 
        else {
            $nextMessageId = $roomController->generateMessageID($room, $data['threadIndex']);
            $message = Message::create([
                'room_id' => $room->id,
                'member_id' => $member->id,
                'message_id' => $nextMessageId,
                'message_role' => 'assistant',
                'model' => $formattedPayload['model'],
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
            ]);
        }

        SendMessage::dispatch($message, $isUpdate)->onQueue('message_broadcast');

        // Update and broadcast final generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => false, // Set to false after generation completes
                'model' => $formattedPayload['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));


    }

    
    private function createRequest($formattedPayload) {
        $user = User::find(1);
        $avatar_url = $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null;

        try {
            // Start the streaming process
            $provider = $this->utilities->getProvider($formattedPayload['model']);
            if($provider['id'] === 'google'){
                $response = $this->modelConnection->requestToGoogle($formattedPayload);
                [$content, $usage] = $this->responseFormatter->formatGoogleResponse($response);
            }
            else{
                $response = $this->modelConnection->requestToAiModel($formattedPayload);
                [$content, $usage] = $this->responseFormatter->formatDefaultResponse($response);
            }

            $this->usageAnalyzer->submitUsageRecord($usage, 'private', $formattedPayload['model']);


            $messageData = [
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $formattedPayload['model'],
                'isDone' => true,
                'content' => $content,
            ];
            return $messageData;

        } catch (\Exception $e) {
            Log::error('Error processing request: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'An error occurred'], 500);
        }
    }


    private function createStream($formattedPayload){
        $user = User::find(1);
        $avatar_url = $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null;
        $firstData = false;
        $onData = function ($data) use ($user, $avatar_url, $formattedPayload) {

            // Decode the JSON chunk
            $chunks = explode("data: ", $data);
            foreach ($chunks as $chunk) {
                // Check if the client has disconnected, and break if true
                if (connection_aborted()) break;
                // Skip any non-JSON or empty chunks
                if (!json_decode($chunk, true) || empty($chunk)) continue;

                [$chunk, $isDone, $usage] = $this->responseFormatter->formatDefaultChunk($chunk);

                if($usage){
                    $this->usageAnalyzer->submitUsageRecord($usage, 'private', $formattedPayload['model']);
                }

                $messageData = [
                    'author' => [
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $avatar_url,
                    ],
                    'model' => $formattedPayload['model'],
                    'isDone' => $isDone,
                    'content' => $chunk,
                ];
                // Directly send the chunk to the client
                echo json_encode($messageData). "\n";
            }
        };

        $this->modelConnection->streamToAiModel($formattedPayload, $onData);        
    }
}
