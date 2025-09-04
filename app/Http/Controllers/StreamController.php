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
        // Increase execution time limit for streaming requests
        set_time_limit(180); // 3 minutes for streaming
        
        // Request-specific buffer for Google Provider
        $requestBuffer = '';
        
        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        

        // Create a callback function to process streaming chunks
        $onData = function ($data) use ($user, $avatar_url, $payload, &$requestBuffer) {

            // Only use normalizeGoogleStreamChunk for Google Provider 
            $provider = $this->aiConnectionService->getProviderForModel($payload['model']);
            $isGoogleProvider = $provider instanceof \App\Services\AI\Providers\GoogleProvider;
            
            if ($isGoogleProvider) {
                // Google sends SSE data which might be split across multiple curl packets
                // We need to normalize this before processing
                $data = $this->normalizeGoogleStreamChunk($data, $requestBuffer);
                
                // If no complete chunks were extracted, return early
                if (empty(trim($data))) {
                    return;
                }
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
     * Handles SSE format with "data: " prefixes and large objects split across packets
     * Improved to handle very large groundingMetadata objects
     */
    private function normalizeGoogleStreamChunk(string $data, string &$requestBuffer): string
    {
        // Add incoming data to buffer
        $requestBuffer .= $data;

        // Handle special case: end of stream marker
        if(trim($requestBuffer) === "]" || trim($requestBuffer) === "data: ]") {
            $requestBuffer = "";
            return "";
        }

        $output = "";
        $extractedCount = 0;
        $maxExtractions = 1000; // Increased for large groundingMetadata
        
        // Look for complete chunks in the buffer
        while($extractedCount < $maxExtractions) {
            $extracted = null;
            
            // First try to find SSE-formatted chunks (data: {JSON})
            if (strpos($requestBuffer, 'data: ') !== false) {
                $extracted = $this->extractSSEChunk($requestBuffer);
            }
            
            // If no SSE chunk found, try to extract raw JSON (for continuation packets)
            if (!$extracted) {
                $extracted = $this->extractJsonObject($requestBuffer);
            }
            
            if (!$extracted) {
                // Check if buffer is getting too large (>10MB) and might be stuck
                if (strlen($requestBuffer) > 10 * 1024 * 1024) {
                    Log::warning('Google Stream: Buffer exceeds 10MB, potential incomplete packet. Clearing buffer.');
                    $requestBuffer = "";
                }
                break; // No more complete chunks
            }
            
            $jsonStr = $extracted['jsonStr'];
            
            // Ensure SSE format for output
            if (!str_starts_with($jsonStr, 'data: ')) {
                $jsonStr = 'data: ' . $jsonStr;
            }
            
            $output .= $jsonStr . "\n";
            $extractedCount++;
        }
        
        // Safety check for infinite loops
        if ($extractedCount >= $maxExtractions) {
            Log::error('Google Stream: Maximum extraction limit reached, clearing buffer to prevent infinite loop');
            $requestBuffer = "";
        }
        
        return $output;
    }
    
    /**
     * Extract SSE-formatted chunk (data: {JSON})
     * Improved to handle very large Google groundingMetadata objects
     */
    private function extractSSEChunk(string &$buffer): ?array
    {
        $dataPos = strpos($buffer, 'data: ');
        if ($dataPos === false) {
            return null;
        }
        
        // Find the JSON part after "data: "
        $jsonStart = $dataPos + 6; // length of "data: "
        
        // For Google responses, we need to find the complete JSON object, not just the next newline
        // because groundingMetadata can be very large and span multiple lines
        $possibleJson = substr($buffer, $jsonStart);
        
        // First, try to find a complete JSON object using brace counting
        // We need to create a copy since extractJsonObject modifies the buffer
        $jsonCopy = $possibleJson;
        $extracted = $this->extractJsonObject($jsonCopy);
        
        if ($extracted) {
            // We found a complete JSON object
            $jsonStr = $extracted['jsonStr'];
            $jsonLength = strlen($jsonStr);
            
            // Calculate the end position in the original buffer
            $jsonEnd = $jsonStart + $jsonLength;
            
            // Extract the complete SSE chunk including "data: " prefix
            $fullChunk = 'data: ' . $jsonStr;
            
            // Update buffer to remove processed chunk
            // Find the next "data: " or end of buffer
            $nextDataPos = strpos($buffer, 'data: ', $jsonEnd);
            if ($nextDataPos !== false) {
                $buffer = substr($buffer, $nextDataPos);
            } else {
                $buffer = ltrim(substr($buffer, $jsonEnd), "\n\r ");
            }
            
            return [
                'jsonStr' => trim($fullChunk)
            ];
        }
        
        // Fallback: Look for newline-based chunks (for simple responses)
        $jsonEnd = strpos($buffer, "\n", $jsonStart);
        
        if ($jsonEnd === false) {
            // No newline found, check if we have a complete JSON object
            if (json_decode(trim($possibleJson), true) !== null) {
                // We have a complete JSON object without newline
                $jsonEnd = strlen($buffer);
            } else {
                return null; // Incomplete chunk
            }
        }
        
        // Extract the complete SSE chunk including "data: " prefix
        $fullChunk = substr($buffer, $dataPos, $jsonEnd - $dataPos);
        
        // Update buffer to remove processed chunk
        $buffer = ltrim(substr($buffer, $jsonEnd), "\n\r ");
        
        return [
            'jsonStr' => trim($fullChunk)
        ];
    }
    
    /**
     * Helper function to extract complete JSON objects from buffer
     * Handles strings properly to avoid false brace detection inside JSON strings
     * Improved to handle Unicode escapes and complex Google responses with large groundingMetadata
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
        $i = $start;
        
        // Increased timeout for very large objects
        $maxIterations = $length * 2; // Allow more iterations for large objects
        $iterations = 0;
        
        while ($i < $length && $iterations < $maxIterations) {
            $char = $buffer[$i];
            $iterations++;
            
            if ($escapeNext) {
                $escapeNext = false;
                $i++;
                continue;
            }
            
            if ($char === '\\') {
                $escapeNext = true;
                $i++;
                continue;
            }
            
            if ($char === '"') {
                $inString = !$inString;
                $i++;
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
                        
                        // Verify that this is valid JSON before proceeding
                        // For large objects, use memory-efficient validation
                        $testDecode = json_decode($jsonStr, true, 512, JSON_INVALID_UTF8_IGNORE);
                        if ($testDecode === null && json_last_error() !== JSON_ERROR_NONE) {
                            // Log the JSON error for debugging
                            Log::warning('Google Stream: Invalid JSON detected - ' . json_last_error_msg() . ' - continuing search');
                            $i++;
                            continue;
                        }
                        
                        $rest = substr($buffer, $end);
                        
                        // Update the buffer reference
                        $buffer = trim($rest);
                        
                        return [
                            'jsonStr' => $jsonStr,
                            'rest' => $rest
                        ];
                    }
                }
            }
            
            $i++;
        }
        
        // Check if we hit the iteration limit
        if ($iterations >= $maxIterations) {
            Log::warning('Google Stream: Hit iteration limit while parsing JSON object, buffer length: ' . strlen($buffer));
        }
        
        // No complete JSON object found
        return null;
    }
    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data)
    {
        // Increase execution time limit for streaming requests
        set_time_limit(180); // 3 minutes for streaming
        
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