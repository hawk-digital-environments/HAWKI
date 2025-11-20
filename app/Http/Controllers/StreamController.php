<?php

namespace App\Http\Controllers;

use App\Events\RoomAiWritingEndedEvent;
use App\Events\RoomAiWritingStartedEvent;
use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;
use App\Models\Room;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiResponse;
use App\Services\Api\ApiRequestMigrator;
use App\Services\Api\Value\ApiRequestFieldConfig;
use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Storage\AvatarStorageService;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly AiService            $aiService,
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
    public function handleAiConnectionRequest(Request $request, ApiRequestMigrator $requestMigrator)
    {
        $request = $requestMigrator->migrate(
            $request,
            new ApiRequestFieldConfig(messageIdField: 'messageId', threadIdField: 'threadIndex')
        );
        
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
                'messageId' => 'nullable|string',
                'threadIndex' => 'nullable|int',
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
                'type' => 'status',
                'data' => [
                    'slug' => $room->slug,
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
        $avatar_url = $this->avatarStorage->getUrl('profile_avatars',
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

        $crypto = new SymmetricCrypto();
        $encryptedData = $crypto->encrypt($response->content['text'],
                                          base64_decode($data['key']));

        // Store message
        $messageHandler = MessageHandlerFactory::create('group');
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        
        if ($isUpdate) {
            $message = $messageHandler->update($room, [
                'message_id' => $data['messageId'],
                'model' => $data['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ]
            ]);
        } else {
            $message = $messageHandler->create($room, [
                'threadId' => $data['threadIndex'],
                'member' => $member,
                'message_role'=> 'assistant',
                'model'=> $data['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ]
            ]);
        }
        
        $broadcastObject = [
            'slug' => $room->slug,
            'message_id'=> $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, $isUpdate)->onQueue('message_broadcast');

        $model = $this->aiService->getModel($data['payload']['model']);
        if ($model) {
            RoomAiWritingEndedEvent::dispatch($room, $model);
        }
        
        // Update and broadcast final generation status
        $generationStatus = [
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => false,
                'model' => $data['payload']['model']
            ]
        ];

        broadcast(new RoomMessageEvent($generationStatus));
    }
}
