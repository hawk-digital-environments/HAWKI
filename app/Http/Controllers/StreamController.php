<?php

namespace App\Http\Controllers;

use App\Http\Controllers\RoomController;

use App\Models\User;
use App\Models\Room;
use App\Models\Message;
use App\Models\Member;


use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\AIConnectionService;
use App\Services\AI\AIProviderFactory;

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

    protected $usageAnalyzer;
    protected $aiConnectionService;
    private $jsonBuffer = '';

    public function __construct(
        UsageAnalyzerService $usageAnalyzer,
        AIConnectionService $aiConnectionService
    ){
        $this->usageAnalyzer = $usageAnalyzer;
        $this->aiConnectionService = $aiConnectionService;
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

            'broadcast' => 'required|boolean',
            'isUpdate' => 'nullable|boolean',
            'messageId' => 'nullable|string',
            'threadIndex' => 'nullable|int', 
            'slug' => 'nullable|string',
            'key' => 'nullable|string',
        ]);


        if ($validatedData['broadcast']) {
            $this->handleGroupChatRequest($validatedData);
        } else {
            $user = User::find(1); // HAWKI user 
            $avatar_url = $user->avatar_id !== '' ? Storage::disk('public')->url('profile_avatars/' . $user->avatar_id) : null;
            
            if ($validatedData['payload']['stream']) {
                // Handle streaming response
                $this->handleStreamingRequest($validatedData['payload'], $user, $avatar_url);
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
                        'username' => $user->username,
                        'name' => $user->name,
                        'avatar_url' => $avatar_url,
                    ],
                    'model' => $validatedData['payload']['model'],
                    'isDone' => true,
                    'content' => json_encode($result['content']),
                ]);
            }
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
                $provider = $this->aiConnectionService->getProviderForModel($payload['model']);
                
                // Format the chunk
                $formatted = $provider->formatStreamChunk($chunk);
                // Log::info('Formatted Chunk:' . json_encode($formatted));

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
        
        // Broadcast initial generation status
        $generationStatus = [
            'type' => 'aiGenerationStatus',
            'messageData' => [
                'room_id' => $room->id,
                'isGenerating' => true,
                'model' => $data['payload']['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));
        
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
        $roomController = new RoomController();
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        
        if ($isUpdate) {
            $message = $room->messages->where('message_id', $data['messageId'])->first();
            $message->update([
                'iv' => $encryptiedData['iv'],
                'tag' => $encryptiedData['tag'],
                'content' => $encryptiedData['ciphertext'],
                'model' => $data['payload']['model'],
            ]);
        } else {
            $nextMessageId = $roomController->generateMessageID($room, $data['threadIndex']);
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
        }
        
        // Queue message for broadcast
        SendMessage::dispatch($message, $isUpdate)->onQueue('message_broadcast');
        
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
