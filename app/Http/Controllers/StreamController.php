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
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\AIConnectionService;
use App\Services\Message\LegacyMessageHelper;
use App\Services\AI\AIProviderFactory;
use App\Services\Chat\Message\MessageHandlerFactory;
use App\Services\Storage\AvatarStorageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class StreamController extends Controller
{

    protected $usageAnalyzer;
    protected $aiConnectionService;
    protected LegacyMessageHelper $messageHelper;
    private $jsonBuffer = '';

    protected $avatarStorage;

    public function __construct(
        UsageAnalyzerService $usageAnalyzer,
        AIConnectionService $aiConnectionService,
        LegacyMessageHelper $threadHelper,
        AvatarStorageService $avatarStorage
    ){
        $this->usageAnalyzer = $usageAnalyzer;
        $this->aiConnectionService = $aiConnectionService;
        $this->avatarStorage = $avatarStorage;
        $this->messageHelper = $threadHelper;
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
        $payload['stream'] = false;

        // Handle standard response
        $result = $this->aiConnectionService->processRequest(
            $payload,
            false
        );

        // Record usage
        if (isset($result['usage'])) {
            $this->usageAnalyzer->submitUsageRecord(
                $result['usage'],
                'api',
                $validatedData['payload']['model']
            );
        }
        // Return response to client
        return response()->json([
            'success' => true,
            'content' => $result['content'],
        ]);
    }




    /**
     * Handle AI connection requests using the new architecture
     */
    public function handleAiConnectionRequest(Request $request)
    {
        //validate payload
        $validatedData = $request->validate([
            'payload.model' => 'required|string',
            'payload.stream' => 'required|boolean',
            'payload.messages' => 'required|array',
            'payload.messages.*.role' => 'required|string',
            'payload.messages.*.content' => 'required|array',
            'payload.messages.*.content.text' => 'required|string',
            'payload.messages.*.content.attachments' => 'nullable|array',

            'broadcast' => 'required|boolean',
            'isUpdate' => 'nullable|boolean',
            'messageId' => 'nullable|string|int',
            'threadIndex' => 'nullable|int',
            'thread_id_version' => 'nullable|int|in:1,2', // 1 for legacy message (192.000) ID, 2 for new message ID (12), defaults to 1
            'slug' => 'nullable|string',
            'key' => 'nullable|string',
        ]);


        if ($validatedData['broadcast']) {
            $room = Room::where('slug', $validatedData['slug'])->firstOrFail();
            // When called via an external "api", the "external_application" default is set on the route, meaning we can check here if the user has access to the model
            $model = app(AIConnectionService::class)->getModelById(
                $validatedData['payload']['model'],
                $request->route('external_app', false)
            );
            
            if (!$model) {
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
            $result = $this->aiConnectionService->processRequest(
                $validatedData['payload'],
                false
            );

            // Record usage
            if (isset($result['usage'])) {
                $this->usageAnalyzer->submitUsageRecord(
                    $result['usage'],
                    'private',
                    $validatedData['payload']['model']
                );
            }

            // Return response to client
            return response()->json([
                'author' => [
                    'username' => $hawki->username,
                    'name' => $hawki->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $validatedData['payload']['model'],
                'isDone' => true,
                'content' => json_encode($result['content']),
            ]);
        }
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


        // Create a callback function to process streaming chunks
        $onData = function ($data) use ($user, $avatar_url, $payload) {
            // Log::debug($data);
          // Only use normaliseDataChunk if the content of $data does not begin with ‘data: ’.
            if (strpos(trim($data), 'data: ') !== 0) {
                $data = $this->normalizeDataChunk($data);
                //Log::info('google chunk detected');
            }
            // Skip non-JSON or empty chunks
            $chunks = explode("data: ", $data);
            foreach ($chunks as $chunk) {
                if (connection_aborted()) break;
                if (!json_decode($chunk, true) || empty($chunk)) continue;

                // Get the provider for this model
                // @todo why not use the container here?
                $factory = new AIProviderFactory();
                $provider = $factory->getProviderForModel($payload['model']);

                // Format the chunk
                $formatted = $provider->formatStreamChunk($chunk);

            // Record usage if available
            if ($formatted['usage']) {
                $this->usageAnalyzer->submitUsageRecord(
                    $formatted['usage'],
                        'private',
                        $payload['model']
                    );
                }

                // Send the formatted response to the client
                $messageData = [
                    'author' => [
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $avatar_url,
                    ],
                    'model' => $payload['model'],
                    'isDone' => $formatted['isDone'],
                    'content' => json_encode($formatted['content']),
                ];
                echo json_encode($messageData) . "\n";
            }
        };

        // Process the streaming request
        $this->aiConnectionService->processRequest(
            $payload,
            true,
            $onData
        );
    }
    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        $this->jsonBuffer .= $data;

        if(trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }

        $output = "";
        while($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $this->jsonBuffer = $extracted['rest'];
            $output .= "data: " . $jsonStr . "\n";
        }
        return $output;
    }

    // New helper function to extract only complete JSON objects from buffer
    private function extractJsonObject(string $buffer): ?array
    {
        $openBraces = 0;
        $startFound = false;
        $startPos = 0;

        for($i = 0; $i < strlen($buffer); $i++) {
            $char = $buffer[$i];
            if($char === '{') {
                if(!$startFound) {
                    $startFound = true;
                    $startPos = $i;
                }
                $openBraces++;
            } elseif($char === '}') {
                $openBraces--;
                if($openBraces === 0 && $startFound) {
                    $jsonStr = substr($buffer, $startPos, $i - $startPos + 1);
                    $rest = substr($buffer, $i + 1);
                    return ['jsonStr' => $jsonStr, 'rest' => $rest];
                }
            }
        }
        return null;
    }
    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data)
    {
        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();
        
        // Process the request
        $result = $this->aiConnectionService->processRequest(
            $data['payload'],
            false
        );

        // Record usage
        if (isset($result['usage'])) {
            $this->usageAnalyzer->submitUsageRecord(
                $result['usage'],
                'group',
                $data['payload']['model'],
                $room->id
            );
        }

        // Encrypt content for storage
        $cryptoController = new EncryptionController();
        $encKey = base64_decode($data['key']);
        $encryptiedData = $cryptoController->encryptWithSymKey($encKey, json_encode($result['content']), false);

        // Store message
        $messageHandler = MessageHandlerFactory::create('group');
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        
        $messageId = $this->messageHelper->getMessageIdInfo($data['messageId']);
        $threadInfo = $this->messageHelper->getThreadInfo(
            $data['threadIndex'],
            ($data['thread_id_version'] ?? 1) === 1
        );
        
        if ($isUpdate) {
            $message = Message::findOrFail($messageId->id);
            $message->update([
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
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
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
            ]);
            MessageSentEvent::dispatch($message);
        }

        // Queue message for broadcast
        SendMessage::dispatch($message, $isUpdate)->onQueue('message_broadcast');
        
        // When called via an external "api", the "external_application" default is set on the route, meaning we can check here if the user has access to the model
        $model = app(AIConnectionService::class)->getModelById($data['payload']['model']);
        
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
