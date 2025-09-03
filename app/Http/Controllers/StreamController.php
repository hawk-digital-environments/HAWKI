<?php

namespace App\Http\Controllers;

use App\Events\MessageSentEvent;
use App\Events\MessageUpdateEvent;
use App\Events\RoomAiWritingEndedEvent;
use App\Events\RoomAiWritingStartedEvent;
use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiResponse;
use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Message\LegacyMessageHelper;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly AiService            $aiService,
        private readonly LegacyMessageHelper  $messageHelper,
        private readonly AvatarStorageService $avatarStorage
    ){
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
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'required|string',
            ]);
        } catch (ValidationException $e) {
            // Return detailed validation error response
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        $payload = $validatedData['payload'];

        // Handle standard response
        $response = $this->aiService->sendRequest($payload);

        // Record usage
        $this->usageAnalyzer->submitUsageRecord($response->usage, 'api');
        
        // Return response to client
        return response()->json([
            'success' => true,
            'content' => $response->content,
        ]);
    }

    /**
     * Handle AI connection requests using the new architecture
     */
    public function handleAiConnectionRequest(Request $request)
    {
        //validate payload
        try {
            $validatedData = $request->validate([
                'payload.model' => 'required|string',
                'payload.stream' => 'required|boolean',
                'payload.messages' => 'required|array',
                'payload.messages.*.role' => 'required|string',
                'payload.messages.*.content' => 'required|array',
                'payload.messages.*.content.text' => 'nullable|string',
                'payload.messages.*.content.attachments' => 'nullable|array',
                'payload.tools' => 'nullable|array',
                
                'broadcast' => 'required|boolean',
                'isUpdate' => 'nullable|boolean',
                'messageId' => ['nullable', function ($_, $value, $fail) {
                    if ($value !== null && !is_string($value) && !is_int($value)) {
                        $fail('The messageId must be a valid numeric string (e.g., "192.000" or "12").');
                    }
                }],
                'threadIndex' => 'nullable|int',
                'thread_id_version' => 'nullable|int|in:1,2', // 1 for legacy message (192.000) ID, 2 for new message ID (12), defaults to 1
                'slug' => 'nullable|string',
                'key' => 'nullable|string',
            ]);
            
            // Ensure that nullable fields are set to default values if not provided
            foreach ($validatedData['payload']['messages'] as &$message) {
                if (isset($message['content']['text']) && !is_string($message['content']['text'])) {
                    $message['content']['text'] = '';
                }
                if (isset($message['content']['attachments']) && !is_array($message['content']['attachments'])) {
                    $message['content']['attachments'] = [];
                }
            }
            unset($message);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation Error',
                'errors' => $e->errors()
            ], 422);
        }

        if ($validatedData['broadcast']) {
            $room = Room::where('slug', $validatedData['slug'])->firstOrFail();
            try {
                $model = $this->aiService->getModelOrFail($validatedData['payload']['model']);
            } catch (\Throwable) {
                return response()->json(['error' => 'The requested model is not available.'], 400);
            }
            
            RoomAiWritingStartedEvent::dispatch($room, $model);
            
            // Broadcast initial generation status immediately
            broadcast(new RoomMessageEvent([
                'type' => 'aiGenerationStatus',
                'messageData' => [
                    'room_id' => Room::where('slug', $validatedData['slug'])->firstOrFail()->id,
                    'isGenerating' => true,
                    'model' => $validatedData['payload']['model']
                ]
            ]));
            
            // We want the request to be handled after the response is sent,
            // so we register a shutdown function to handle the group chat request
            // This allows the client to receive a response immediately while processing continues in the background
            // This is useful for group chat requests where we don't want to block the client waiting for the AI response
            // and we want to handle the request asynchronously.
            // Note: This is not the best practice and should be migrated into a proper job queue in the future.
            register_shutdown_function(function () use ($validatedData) {
                $this->handleGroupChatRequest($validatedData);
            });
            
            return response()->json(['success' => true]);
        }
        
        $hawki = User::find(1); // HAWKI user
        $avatar_url = $this->avatarStorage->getFileUrl('profile_avatars',
                                            $hawki->username,
                                            $hawki->avatar_id);

        if ($validatedData['payload']['stream']) {
            // Handle streaming response
            $this->handleStreamingRequest($validatedData['payload'], $hawki, $avatar_url);
        } else {
            // Handle standard response
            $response = $this->aiService->sendRequest($validatedData['payload']);
            
            $this->usageAnalyzer->submitUsageRecord($response->usage, 'private');

            // Return response to client
            return response()->json([
                'author' => [
                    'username' => $hawki->username,
                    'name' => $hawki->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $validatedData['payload']['model'],
                'isDone' => true,
                'content' => json_encode($response->content),
            ]);
        }
        
        return null;
    }

    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
    {
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        $onData = function (AiResponse $response) use ($user, $avatar_url, $payload) {
            $flush = static function () {
                if (ob_get_length()) {
                    ob_flush();
                }
                flush();
            };
            
            $this->usageAnalyzer->submitUsageRecord(
                $response->usage,
                'private',
            );
            
            $messageData = [
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $payload['model'],
                'isDone' => $response->isDone,
                'content' => json_encode($response->content),
            ];
            
            echo json_encode($messageData) . "\n";
            $flush();
        };
        
        $this->aiService->sendStreamRequest($payload, $onData);
    }
    
    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data): void
    {
        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();
        
        // Process the request
        $response = $this->aiService->sendRequest($data['payload']);

        // Record usage
        $this->usageAnalyzer->submitUsageRecord(
            $response->usage,
            'group',
            $room->id
        );

        // Encrypt content for storage
        $cryptoController = new EncryptionController();
        $encKey = base64_decode($data['key']);
        $encryptedData = $cryptoController->encryptWithSymKey($encKey, json_encode($response->content), false);

        // Store message
        $messageHandler = MessageHandlerFactory::create('group');
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        
        $threadInfo = $this->messageHelper->getThreadInfo(
            $data['threadIndex'],
            ($data['thread_id_version'] ?? 1) === 1
        );
        
        if ($isUpdate) {
            $messageId = $this->messageHelper->getMessageIdInfo($data['messageId']);
            $message = Message::findOrFail($messageId->id);
            $message->update([
                'iv' => $encryptedData['iv'],
                'tag' => $encryptedData['tag'],
                'content' => $encryptedData['ciphertext'],
                'model' => $data['payload']['model'],
            ]);
            MessageUpdateEvent::dispatch($message);
        } else {
            $nextMessageId = $messageHandler->assignID($room, $threadInfo->legacyThreadId);
            $message = Message::create([
                'room_id' => $room->id,
                'member_id' => $member->id,
                'message_id' => $nextMessageId,
                'message_role' => 'assistant',
                'model' => $data['payload']['model'],
                'iv' => $encryptedData['iv'],
                'tag' => $encryptedData['tag'],
                'content' => $encryptedData['ciphertext'],
            ]);
            MessageSentEvent::dispatch($message);
        }

        // Queue message for broadcast
        SendMessage::dispatch($message, $isUpdate)->onQueue('message_broadcast');
        
        $model = $this->aiService->getModel($data['payload']['model']);
        if ($model) {
            RoomAiWritingEndedEvent::dispatch($room, $model);
        }
        
        // Update and broadcast final generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => false,
                'model' => $data['payload']['model']
            ]
        ];
        
        broadcast(new RoomMessageEvent($generationStatus));
    }
}
