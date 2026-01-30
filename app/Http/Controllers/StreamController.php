<?php

namespace App\Http\Controllers;

use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;

use App\Models\Room;
use App\Models\User;
use App\Services\AI\AiService;
use App\Services\AI\UsageAnalyzerService;
use App\Services\AI\Value\AiResponse;
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
                'payload.messages.*.content.auxiliaries' => 'nullable|array',
                'payload.tools' => 'nullable|array',                'payload.reasoning_effort' => 'nullable|string|in:low,medium,high',
                'broadcast' => 'required|boolean',
                'isUpdate' => 'nullable|boolean',
                'messageId' => ['nullable', function ($_, $value, $fail) {
                    if ($value !== null && !is_string($value) && !is_int($value)) {
                        $fail('The messageId must be a valid numeric string (e.g., "192.000" or "12").');
                    }
                }],
                'threadIndex' => 'nullable|int',
                'slug' => 'nullable|string',
                'key' => 'nullable|string',
                'assistantKey' => 'nullable|string|in:title_generator,prompt_improver,summarizer',
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
            $this->handleGroupChatRequest($validatedData);
            return null;
        }

        $hawki = User::find(1); // HAWKI user
        $avatar_url = $this->avatarStorage->getUrl('profile_avatars',
                                            $hawki->username,
                                            $hawki->avatar_id);

        // Determine usage type based on assistantKey
        $usageType = $this->determineUsageType($validatedData['assistantKey'] ?? null);

        if ($validatedData['payload']['stream']) {
            // Handle streaming response
            try {
                $this->handleStreamingRequest($validatedData['payload'], $hawki, $avatar_url, $usageType);
            } catch (\Exception $e) {
                // Error is logged in handleStreamingRequest, just re-throw
                throw $e;
            }
        } else {
            // Handle standard response (non-streaming)
            // Create pending usage record
            $usageRecord = $this->usageAnalyzer->createPendingRecord(
                type: $usageType,
                model: $validatedData['payload']['model'],
                apiProvider: null,
            );
            
            try {
                $response = $this->aiService->sendRequest($validatedData['payload']);

                // Update with actual usage
                $this->usageAnalyzer->updateRecord($usageRecord, $response->usage, 'success');

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
            } catch (\Exception $e) {
                // Mark as failed
                $this->usageAnalyzer->updateRecord($usageRecord, null, 'failed');
                throw $e;
            }
        }

        return null;
    }

    /**
     * Determine usage tracking type based on assistant key
     * 
     * @param string|null $assistantKey
     * @return string
     */
    private function determineUsageType(?string $assistantKey): string
    {
        return match ($assistantKey) {
            'title_generator' => 'title',
            'prompt_improver' => 'improver',
            'summarizer' => 'summarizer',
            default => 'private',
        };
    }

    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(array $payload, User $user, ?string $avatar_url, string $usageType = 'private')
    {
        // Check if stream buffering should be disabled (legacy config - still supported)
        $disableBuffering = config('system.disable_stream_buffering', true);

        if ($disableBuffering) {
            // Disable all output buffering for real-time streaming
            while (ob_get_level() > 0) {
                ob_end_clean();
            }
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        
        // Performance Optimization 1: Disable Nginx proxy buffering
        // Impact: High - Most critical for reducing latency with Nginx/reverse proxies
        if (config('system.stream_disable_nginx_buffering', true)) {
            header('X-Accel-Buffering: no');
        }

        // Performance Optimization 2: Disable Apache gzip compression
        // Impact: Medium - Reduces buffering on Apache servers
        if (config('system.stream_disable_apache_gzip', true)) {
            if (function_exists('apache_setenv')) {
                apache_setenv('no-gzip', '1');
            }
        }

        // Performance Optimization 3: Disable PHP output buffering
        // Impact: Variable - Can cause ~4 seconds lag in some configurations
        // WARNING: Test thoroughly before enabling
        if (config('system.stream_disable_php_output_buffering', false)) {
            ini_set('output_buffering', 'off');
        }
        
        // Performance Optimization 4: Disable PHP zlib compression
        // Impact: Medium - Reduces compression overhead during streaming
        if (config('system.stream_disable_zlib_compression', true)) {
            ini_set('zlib.output_compression', 'off');
        }

        // Create pending usage record at request start
        $usageRecord = $this->usageAnalyzer->createPendingRecord(
            type: $usageType,
            model: $payload['model'],
            apiProvider: null,
        );

        $streamCompleted = false; // Track if stream completed normally
        
        // Register shutdown function to handle cancellations and unhandled errors
        $usageAnalyzer = $this->usageAnalyzer; // Capture for closure
        register_shutdown_function(function() use ($usageRecord, &$streamCompleted, $usageAnalyzer) {
            // Reload the record to get current status from DB
            $usageRecord->refresh();
            
            // Only update if status is still NULL (not already updated by success/failed logic)
            if ($usageRecord->status === null) {
                // Log for debugging
                \Log::info('Shutdown function handling NULL status record', [
                    'record_id' => $usageRecord->id,
                    'stream_completed' => $streamCompleted
                ]);
                
                if (!$streamCompleted) {
                    // Stream didn't complete and no error was caught = user cancelled or connection lost
                    $usageAnalyzer->updateRecord($usageRecord, null, 'cancelled');
                } else {
                    // Stream completed but no usage data = unexpected state, mark as failed
                    $usageAnalyzer->updateRecord($usageRecord, null, 'failed');
                }
            }
        });

        try {
            $onData = function (AiResponse $response) use ($user, $avatar_url, $payload, $usageRecord, &$streamCompleted) {
      
                $flush = static function () {
                    // Force flush immediately
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                };

                // DISABLED: Log usage data (causes streaming delay)
                // if (config('logging.triggers.usage') && $response->usage) {
                //     \Log::info('Token Usage Data', [
                //         'model' => $payload['model'],
                //         'prompt_tokens' => $response->usage->promptTokens,
                //         'completion_tokens' => $response->usage->completionTokens,
                //         'total_tokens' => $response->usage->promptTokens + $response->usage->completionTokens
                //     ]);
                // }

                // Update usage record when we receive usage data
                if ($response->usage) {
                    $this->usageAnalyzer->updateRecord($usageRecord, $response->usage, 'success');
                }
                
                // Mark stream as completed when isDone is true
                if ($response->isDone) {
                    $streamCompleted = true;
                }

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
            
            // Mark as cancelled if stream didn't complete
            if (!$streamCompleted) {
                $this->usageAnalyzer->updateRecord($usageRecord, null, 'cancelled');
            }
        } catch (\Exception $e) {
            // Mark as failed on exception
            $this->usageAnalyzer->updateRecord($usageRecord, null, 'failed');
            
            // Refresh the record so shutdown function sees the updated status
            $usageRecord->refresh();
            
            // Log the error for debugging
            \Log::error('Streaming request failed', [
                'user_id' => $user->id,
                'model' => $payload['model'],
                'error' => $e->getMessage(),
                'usage_record_id' => $usageRecord->id
            ]);
            
            throw $e;
        } catch (\Throwable $e) {
            // Catch ALL errors including Fatal Errors
            $this->usageAnalyzer->updateRecord($usageRecord, null, 'failed');
            $usageRecord->refresh();
            
            \Log::error('Fatal error in streaming request', [
                'user_id' => $user->id,
                'model' => $payload['model'],
                'error' => $e->getMessage(),
                'usage_record_id' => $usageRecord->id
            ]);
            
            throw $e;
        }
    }

    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data): void
    {
        $isUpdate = (bool) ($data['isUpdate'] ?? false);
        $room = Room::where('slug', $data['slug'])->firstOrFail();

        // Broadcast initial generation status
        $generationStatus = [
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => true,
                'model' => $data['payload']['model']
            ]
        ];
        broadcast(new RoomMessageEvent($generationStatus));

        // Create pending usage record
        $usageRecord = $this->usageAnalyzer->createPendingRecord(
            type: 'group',
            model: $data['payload']['model'],
            apiProvider: null,
            roomId: $room->id
        );

        try {
            // Process the request
            $response = $this->aiService->sendRequest($data['payload']);

            // Update with actual usage
            $this->usageAnalyzer->updateRecord($usageRecord, $response->usage, 'success');

            $crypto = new SymmetricCrypto();
            $encryptedTextData = $crypto->encrypt($response->content['text'],
                                                  base64_decode($data['key']));

            // Build content structure with encrypted text
            $content = [
                'text' => [
                    'ciphertext' => base64_encode($encryptedTextData->ciphertext),
                    'iv' => base64_encode($encryptedTextData->iv),
                    'tag' => base64_encode($encryptedTextData->tag),
                ]
            ];

            // Encrypt and add auxiliaries if present
            if (isset($response->content['auxiliaries']) && !empty($response->content['auxiliaries'])) {
                \Log::info('[GROUPCHAT] Encrypting auxiliaries', [
                    'count' => count($response->content['auxiliaries']),
                    'types' => array_column($response->content['auxiliaries'], 'type')
                ]);
                
                $auxiliariesJson = json_encode($response->content['auxiliaries']);
                $encryptedAuxiliariesData = $crypto->encrypt($auxiliariesJson, base64_decode($data['key']));
                
                $content['auxiliaries'] = [
                    'ciphertext' => base64_encode($encryptedAuxiliariesData->ciphertext),
                    'iv' => base64_encode($encryptedAuxiliariesData->iv),
                    'tag' => base64_encode($encryptedAuxiliariesData->tag),
                ];
                
                \Log::info('[GROUPCHAT] Auxiliaries encrypted and added to content');
            } else {
                \Log::warning('[GROUPCHAT] No auxiliaries to encrypt', [
                    'has_auxiliaries_key' => isset($response->content['auxiliaries']),
                    'auxiliaries_value' => $response->content['auxiliaries'] ?? 'not set'
                ]);
            }

            // Store message
            $messageHandler = MessageHandlerFactory::create('group');
            $member = $room->members()->where('user_id', 1)->firstOrFail();

            if ($isUpdate) {
                $message = $messageHandler->update($room, [
                    'message_id' => $data['messageId'],
                    'model' => $data['payload']['model'],
                    'content' => $content
                ]);
            } else {
                $message = $messageHandler->create($room, [
                    'threadId' => $data['threadIndex'],
                    'member' => $member,
                    'message_role'=> 'assistant',
                    'model'=> $data['payload']['model'],
                    'content' => $content
                ]);
            }


            $broadcastObject = [
                'slug' => $room->slug,
                'message_id'=> $message->message_id,
            ];
            SendMessage::dispatch($broadcastObject, $isUpdate)->onQueue('message_broadcast');

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
            
        } catch (\Exception $e) {
            // Mark as failed
            $this->usageAnalyzer->updateRecord($usageRecord, null, 'failed');
            
            // Broadcast error status
            $errorStatus = [
                'type' => 'status',
                'data' => [
                    'slug' => $room->slug,
                    'isGenerating' => false,
                    'error' => true,
                    'model' => $data['payload']['model'] ?? 'unknown'
                ]
            ];
            broadcast(new RoomMessageEvent($errorStatus));
            
            throw $e;
        }
    }
}
