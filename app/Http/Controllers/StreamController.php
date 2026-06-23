<?php

namespace App\Http\Controllers;

use App\Events\RoomAiWritingEndedEvent;
use App\Events\RoomAiWritingStartedEvent;
use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;
use App\Models\Room;
use App\Models\User;
use App\Services\Ai\Agent\Chat\Values\ChatRequest;
use App\Services\Ai\Agent\Chat\Values\ChatResponse;
use App\Services\Ai\Agent\Chat\Values\StreamingChatResponse;
use App\Services\Ai\AiService;
use App\Services\Ai\UsageAnalyzerService;
use App\Services\Ai\Values\Chunks\MaxToolExecutionsChunk;
use App\Services\Ai\Values\Chunks\StreamDoneChunk;
use App\Services\Ai\Values\TokenUsage;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\Storage\AvatarStorageService;
use App\Services\Storage\Values\StoredFileIdentifier;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use NeuronAI\Chat\Messages\Stream\Chunks\ReasoningChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\TextChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolCallChunk;
use NeuronAI\Chat\Messages\Stream\Chunks\ToolResultChunk;
use Psr\Log\LoggerInterface;

class StreamController extends Controller
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly AiService            $aiService,
        private readonly AvatarStorageService $avatarStorage,
        private readonly GroupMessageHandler  $groupMessageHandler,
        private readonly LoggerInterface      $logger
    )
    {
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
        $response = $this->aiService->sendRequestToAgent($payload);

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
                'payload.params' => 'nullable|array',

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
                $model = $this->aiService->getModels()->findOneOrFail($validatedData['payload']['model']);
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
            register_shutdown_function(function () use ($validatedData, $request) {
                $this->handleGroupChatRequest($validatedData, $request);
            });

            return response()->json(['success' => true]);
        }

        $hawki = User::find(1); // HAWKI user
        $avatar_url = $this->avatarStorage->retrieve(StoredFileIdentifier::tryFromUserAvatar($hawki))?->getUrl();

        try {
            $agentRequest = $this->aiService->getAgentRequestFactory()->createFromPayload($validatedData['payload']);
        } catch (\Throwable $e) {
            $this->logger->error('Error creating agent request from payload', ['exception' => $e]);
            return response()->json(['success' => false], 400);
        }

        try {
            $response = $this->aiService->sendRequestToAgent($agentRequest);
        } catch (\Throwable $e) {
            $this->logger->error('Error sending request to agent', ['exception' => $e]);
            return response()->json(['success' => false], 500);
        }

        if ($agentRequest instanceof ChatRequest && $response instanceof StreamingChatResponse) {
            $this->handleStreamingRequest($agentRequest, $response, $hawki, $avatar_url);
            return null;
        }

        if (!$response instanceof ChatResponse) {
            $this->logger->error('Unexpected response type from agent', ['response' => $response]);
            return response()->json(['success' => false], 500);
        }

//        $this->usageAnalyzer->submitUsageRecord($response->usage, 'private');

        // Return response to client
        return response()->json([
            'author' => [
                'username' => $hawki->username,
                'name' => $hawki->name,
                'avatar_url' => $avatar_url,
            ],
            'model' => $validatedData['payload']['model'],
            'isDone' => true,
            'content' => json_encode([
                'text' => $response->content
            ]),
            'tools' => $validatedData['payload']['tools'] ?? null,
        ]);
    }

    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(ChatRequest $request, StreamingChatResponse $response, User $user, ?string $avatar_url): void
    {
        // Disable output buffering
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        // Set headers for SSE
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('Access-Control-Allow-Origin: *');
        header('X-Accel-Buffering: no');

        $sendData = function (
            string     $content,
            string     $type,
            bool       $isDone = false,
            array|null $status = null
        ) use ($user, $avatar_url, $request) {
            $messageData = [
                'author' => [
                    'username' => $user->username,
                    'name' => $user->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $request->model->model_id,
                'tools' => $request->tools,
                'isDone' => $isDone,
                'content' => json_encode([
                    'text' => $content
                ]),
                'type' => $type,
                'status' => $status,
            ];

            echo json_encode($messageData) . "\n";
            flush();
        };

        $sendStatus = function (string $statusKey, mixed $value) use ($sendData) {
            $sendData(
                content: '',
                type: 'status',
                isDone: false,
                status: [
                    'key' => $statusKey,
                    'value' => $value
                ]
            );
        };

        $lastChunkType = null;

        $sendStatusOnChange = function (string $chunkType, string $statusKey, mixed $value) use (&$lastChunkType, $sendStatus) {
            if ($lastChunkType === null || $chunkType !== $lastChunkType) {
                $sendStatus($statusKey, $value);
            }
        };

        foreach ($response->chunks() as $chunk) {
            if ($chunk instanceof TextChunk) {
                $sendData(
                    content: $chunk->content,
                    type: 'message',
                    isDone: false,
                );
            }
            if ($chunk instanceof ReasoningChunk) {
                $sendStatusOnChange(ReasoningChunk::class, 'reasoning', 'Thinking...');
                $sendData(
                    content: $chunk->content,
                    type: 'message',
                    isDone: false,
                );
            }
            if ($chunk instanceof ToolCallChunk) {
                $sendStatusOnChange(ToolCallChunk::class, 'tool_call', [$chunk->tool->getName()]);
            }
            if ($chunk instanceof ToolResultChunk) {
                $sendStatusOnChange(ToolResultChunk::class, 'tool_result', 'Received result from tool: ' . $chunk->tool->getName());
            }
            if ($chunk instanceof MaxToolExecutionsChunk) {
                $sendStatus('max_execution', 'Maximum tool execution rounds reached. Generating final response...');
            }
            if ($chunk instanceof StreamDoneChunk) {
                $sendData(content: '', type: 'message', isDone: true);
                $this->usageAnalyzer->submitUsageRecord(
                    new TokenUsage(
                        model: $request->model,
                        promptTokens: $chunk->getMessage()->getUsage()->inputTokens,
                        completionTokens: $chunk->getMessage()->getUsage()->outputTokens,
                    ),
                    'private',
                );
                return;
            }

            $lastChunkType = get_class($chunk);
        }
    }

    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $data, Request $request): void
    {
        // @todo is $request still needed here
        $isUpdate = (bool)($data['isUpdate'] ?? false);
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

        $data['payload']['stream'] = false; // Ensure streaming is disabled for group chat requests
        try {
            $agentRequest = $this->aiService->getAgentRequestFactory()->createFromPayload($data['payload']);
        } catch (\Throwable $e) {
            $this->logger->error('Error creating agent request from payload', ['exception' => $e]);
            try {
                $model = $this->aiService->getModels()->findOneOrFail($data['payload']['model']);
                RoomAiWritingEndedEvent::dispatch($room, $model);
            } catch (\Throwable) {
            }
            abort(400, 'Invalid payload for agent request.');

        }

        // Process the request
        $response = $this->aiService->sendRequestToAgent($agentRequest);
        if (!$response instanceof ChatResponse) {
            $this->logger->error('Unexpected response type from agent', ['response' => $response]);
            try {
                $model = $this->aiService->getModels()->findOneOrFail($data['payload']['model']);
                RoomAiWritingEndedEvent::dispatch($room, $model);
            } catch (\Throwable) {
            }
            abort(500, 'Unexpected response type from agent.');
        }

        // Record usage
//        $this->usageAnalyzer->submitUsageRecord(
//            $response->usage,
//            'group',
//            $room->id
//        );

        // @todo this was         $crypto = new SymmetricCrypto();
        //        $encryptedData = $crypto->encrypt($response->content['text'],
        //                                          base64_decode($data['key']));
        $content = $response->content;
//        $content = $response->content;
//        if (array_key_exists('groundingMetadata', $response->content)) {
//            $content = json_encode([
//                'text' => $response->content['text'],
//                'groundingMetadata' => $response->content['groundingMetadata'],
//            ]);
//        }
//        \Log::debug($content);
        $encryptedData = (new SymmetricCrypto())->encrypt(json_encode($content), base64_decode($data['key']));

        // Store message
        $member = $room->members()->where('user_id', 1)->firstOrFail();

        if ($isUpdate) {
            $message = $this->groupMessageHandler->update($room, [
                'message_id' => $data['messageId'],
                'model' => $data['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ],
                'metadata' => [
                    'tools' => $data['payload']['tools'] ?? null,
                    'params' => $data['payload']['params'] ?? null,
                ],
            ]);
        } else {
            $message = $this->groupMessageHandler->create(
                $room,
                [
                    'threadId' => $data['threadIndex'],
                    'member' => $member,
                    'message_role' => 'assistant',
                    'model' => $data['payload']['model'],
                    'content' => [
                        'text' => [
                            'ciphertext' => base64_encode($encryptedData->ciphertext),
                            'iv' => base64_encode($encryptedData->iv),
                            'tag' => base64_encode($encryptedData->tag),
                        ]
                    ],
                    'metadata' => [
                        'tools' => $data['payload']['tools'] ?? null,
                        'params' => $data['payload']['params'] ?? null,
                    ],
                ],
                $request->user()
            );
        }

        $broadcastObject = [
            'slug' => $room->slug,
            'message_id' => $message->message_id,
        ];
        SendMessage::dispatch($broadcastObject, $isUpdate)->onQueue('message_broadcast');

        $model = $this->aiService->getModels()->findOne($data['payload']['model']);
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
