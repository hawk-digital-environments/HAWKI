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
            $avatar_url = $user->avatar_id !== '' ? config('app.url') . '/storage/profile_avatars/' . $user->avatar_id : null;
            
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
                    'content' => $result['content'],
                ]);
            }
        }
    }
    
    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url)
    {
        // Reset buffer for new request
        $this->jsonBuffer = '';
        
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
            // Log::info('Google Stream: Split into ' . count($chunks) . ' chunks');
            
            foreach ($chunks as $chunkIndex => $chunk) {
                if (connection_aborted()) break;
                if (empty(trim($chunk))) {
                    // Log::info("Google Stream: Chunk $chunkIndex is empty, skipping");
                    continue;
                }
                
                $jsonData = json_decode(trim($chunk), true);
                if (!$jsonData) {
                    // Log::info("Google Stream: Chunk $chunkIndex is not valid JSON: " . substr($chunk, 0, 100));
                    continue;
                }
                
                // Get the provider for this model
                $provider = $this->aiConnectionService->getProviderForModel($payload['model']);
                
                // Format the chunk
                $formatted = $provider->formatStreamChunk(trim($chunk));
                // Log::info('Google Stream: Formatted chunk ' . $chunkIndex . ', content length: ' . strlen($formatted['content']['text']) . ', isDone: ' . ($formatted['isDone'] ? 'true' : 'false'));

                // Record usage if available
                if ($formatted['usage']) {
                    $this->usageAnalyzer->submitUsageRecord(
                        $formatted['usage'], 
                        'private', 
                        $payload['model']
                    );
                }
                
                // Special handling for Google Provider: 
                // If this is a final chunk with content, send content first, then completion
                $isGoogleProvider = $provider instanceof \App\Services\AI\Providers\GoogleProvider;
                if ($isGoogleProvider && $formatted['isDone'] && !empty($formatted['content']['text'])) {
                    // Send content chunk first
                    $contentMessage = [
                        'author' => [
                            'username' => $user->username,
                            'name' => $user->name,
                            'avatar_url' => $avatar_url,
                        ],
                        'model' => $payload['model'],
                        'isDone' => false, // Not done yet
                        'content' => json_encode($formatted['content']),
                    ];
                    
                    echo json_encode($contentMessage) . "\n";
                    if (ob_get_length()) ob_flush();
                    flush();
                    
                    // Then send completion chunk
                    $completionMessage = [
                        'author' => [
                            'username' => $user->username,
                            'name' => $user->name,
                            'avatar_url' => $avatar_url,
                        ],
                        'model' => $payload['model'],
                        'isDone' => true, // Now we're done
                        'content' => json_encode(['text' => '', 'groundingMetadata' => '']),
                    ];
                    
                    echo json_encode($completionMessage) . "\n";
                    if (ob_get_length()) ob_flush();
                    flush();
                } else {
                    // Normal handling for all other providers
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
                    if (ob_get_length()) ob_flush();
                    flush();
                }
                
                // Debug logging for final chunks
                if ($formatted['isDone']) {
                    Log::info('StreamController: Final chunk detected - isDone=true, content: ' . substr($formatted['content']['text'], 0, 100));
                }
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
     * Uses request-specific buffer instead of instance variable
     */
    private function normalizeGoogleStreamChunk(string $data, string &$requestBuffer): string
    {
        // Log incoming raw data for debugging
        Log::info('Google Stream Raw Data: ' . substr($data, 0, 200) . (strlen($data) > 200 ? '...' : ''));
        
        $requestBuffer .= $data;

        if(trim($requestBuffer) === "]") {
            $requestBuffer = "";
            return "";
        }

        $output = "";
        while($extracted = $this->extractJsonObject($requestBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $output .= "data: " . $jsonStr . "\n";
            
            // Log what we're outputting
            Log::info('Google Stream Normalized Chunk: ' . substr($jsonStr, 0, 100) . (strlen($jsonStr) > 100 ? '...' : ''));
        }
        
        // Log remaining buffer if any
        if (!empty($requestBuffer)) {
            Log::info('Google Stream Buffer Remaining: ' . substr($requestBuffer, 0, 100) . (strlen($requestBuffer) > 100 ? '...' : ''));
        }
        
        return $output;
    }
    
    /*
     * Helper function to translate curl return object from google to openai format
     */
    private function normalizeDataChunk(string $data): string
    {
        // Log incoming raw data for debugging (uncomment for debugging)
        Log::info('Google Stream Raw Data: ' . substr($data, 0, 200) . (strlen($data) > 200 ? '...' : ''));
        
        $this->jsonBuffer .= $data;

        if(trim($this->jsonBuffer) === "]") {
            $this->jsonBuffer = "";
            return "";
        }

        $output = "";
        $extractedCount = 0;
        
        // Extract all complete JSON objects from buffer
        while($extracted = $this->extractJsonObject($this->jsonBuffer)) {
            $jsonStr = $extracted['jsonStr'];
            $output .= "data: " . $jsonStr . "\n";
            $extractedCount++;
            
            // Log what we're outputting (uncomment for debugging)
            Log::info('Google Stream Normalized Chunk ' . $extractedCount . ': ' . substr($jsonStr, 0, 100) . (strlen($jsonStr) > 100 ? '...' : ''));
            
            // Prevent infinite loops (safety measure)
            if ($extractedCount > 100) {
                Log::error('Google Stream: Too many JSON objects extracted, breaking to prevent infinite loop');
                break;
            }
        }
        
        // Log remaining buffer if any (uncomment for debugging)
        if (!empty($this->jsonBuffer)) {
            Log::info('Google Stream Buffer Remaining: ' . substr($this->jsonBuffer, 0, 100) . (strlen($this->jsonBuffer) > 100 ? '...' : ''));
        }
        
        return $output;
    }

    /**
     * Helper function to extract complete JSON objects from buffer
     * Handles strings properly to avoid false brace detection inside JSON strings
     */
    private function extractJsonObject(string &$buffer): ?array
    {
        $buffer = trim($buffer);
        
        if (empty($buffer)) {
            return null;
        }
        
        // Find the start of a JSON object
        $start = strpos($buffer, '{');
        if ($start === false) {
            return null;
        }
        
        // Track brace depth to find the complete JSON object
        $depth = 0;
        $inString = false;
        $escapeNext = false;
        $length = strlen($buffer);
        
        for ($i = $start; $i < $length; $i++) {
            $char = $buffer[$i];
            
            if ($escapeNext) {
                $escapeNext = false;
                continue;
            }
            
            if ($char === '\\') {
                $escapeNext = true;
                continue;
            }
            
            if ($char === '"') {
                $inString = !$inString;
                continue;
            }
            
            if (!$inString) {
                if ($char === '{') {
                    $depth++;
                } elseif ($char === '}') {
                    $depth--;
                    
                    // Found complete JSON object
                    if ($depth === 0) {
                        $end = $i + 1;
                        $jsonStr = substr($buffer, $start, $end - $start);
                        $rest = substr($buffer, $end);
                        
                        // Update the buffer reference
                        $buffer = $rest;
                        
                        return [
                            'jsonStr' => $jsonStr,
                            'rest' => $rest
                        ];
                    }
                }
            }
        }
        
        // No complete JSON object found
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
