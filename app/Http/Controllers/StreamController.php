<?php

namespace App\Http\Controllers;

use App\Events\RoomMessageEvent;
use App\Jobs\SendMessage;
use App\Models\Ai\AiModel;
use App\Models\Room;
use App\Services\Ai\AiService;
use App\Services\Ai\UsageAnalyzerService;
use App\Services\Chat\Events\RoomAiWritingEndedEvent;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\ExternalContent\CitationUrlCleaner;
use App\Services\Storage\AvatarStorageService;
use App\Services\Users\Repositories\UserRepository;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\ProviderToolEvent;
use Laravel\Ai\Streaming\Events\ReasoningDelta;
use Laravel\Ai\Streaming\Events\ReasoningStart;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use Psr\Log\LoggerInterface;

class StreamController extends Controller
{
    public function __construct(
        private readonly UsageAnalyzerService $usageAnalyzer,
        private readonly AiService            $aiService,
        private readonly AvatarStorageService $avatarStorage,
        private readonly GroupMessageHandler  $groupMessageHandler,
        private readonly LoggerInterface      $logger,
        private readonly UserRepository       $userRepository,
        private readonly CitationUrlCleaner   $citationCleaner
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

        // Handle standard response
        $agent = $this->aiService->getAgent($validatedData);
        $response = $agent->send();

        // Record usage
        $this->usageAnalyzer->submitUsageRecord($agent->getUsage(), 'api');

        // Return response to client
        return response()->json([
            'success' => true,
            'content' => $response->text,
        ]);
    }

    /**
     * Handle AI connection requests using the new architecture
     */
    public function handleAiConnectionRequest(Request $request)
    {
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
            register_shutdown_function(function () use ($validatedData, $model) {
                $this->handleGroupChatRequest($validatedData, $model);
            });

            return response()->json(['success' => true]);
        }

        if ($validatedData['payload']['stream'] === false) {
            return response()->json($this->handleNonStreamingRequest($validatedData));
        }

        try {
            return response()->stream(
                callback: fn() => yield from $this->handleStreamingRequest($validatedData),
                headers: [
                    'Content-Type' => 'text/event-stream',
                    'Cache-Control' => 'no-cache',
                    // @todo I deem those two weird in this context, but they are in the original code, so I keep them for now. I will investigate if they are needed.
                    'Connection' => 'keep-alive',
                    'Access-Control-Allow-Origin' => '*'
                ]);
        } catch (RequestException $e) {
            $this->logger->error('RequestException while streaming response of agent', [
                'exception' => $e,
                'response' => $e->response->body()
            ]);
            return response()->json(['success' => false], 500);
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error while streaming response of agent', ['exception' => $e]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Handle streaming request with the new architecture
     */
    private function handleStreamingRequest(
        array $validatedData
    ): iterable
    {
        $hawki = $this->userRepository->findHawki();
        $avatar_url = $this->avatarStorage->retrieveAvatar($hawki)?->getUrl();

        $formatError = static fn(string $errorMessage) => json_encode([
                'content' => $errorMessage,
                'type' => 'error',
                'isDone' => true
            ], JSON_THROW_ON_ERROR) . "\n";

        $formatData = function (
            string|object $content,
            string        $type,
            bool          $isDone = false,
            array|null    $status = null,
            array|null    $additionalData = null
        ) use ($formatError) {
            $messageData = array_merge(
                $additionalData ?? [],
                [
                    'isDone' => $isDone,
                    'content' => $content,
                    'type' => $type,
                    'status' => $status,
                ]
            );

            try {
                return json_encode($messageData, JSON_THROW_ON_ERROR) . "\n";
            } catch (\Throwable $e) {
                $this->logger->error('Error encoding message data to JSON', [
                    'exception' => $e,
                    'messageData' => array_merge(
                        $messageData,
                        ['author' => '**hidden**', 'content' => '**hidden**']
                    )
                ]);

                return $formatError('There was an HAWKI internal issue. Please try again later.');
            }
        };

        $formatStatus = static fn(string $statusKey, mixed $value = '') => $formatData(
            content: '',
            type: 'status',
            isDone: false,
            status: [
                'key' => $statusKey,
                'value' => $value
            ]
        );

        yield $formatData(
            content: '',
            type: 'header',
            isDone: false,
            status: null,
            additionalData: [
                'author' => [
                    'username' => $hawki->username,
                    'name' => $hawki->name,
                    'avatar_url' => $avatar_url,
                ],
                'model' => $validatedData['payload']['model'] ?? 'unknown',
                'tools' => $validatedData['payload']['tools'] ?? [],
            ]
        );

        try {
            $agent = $this->aiService->getAgent($validatedData);

            $res = $agent->sendStreaming();

            // We batch the citations first, so we can clean them all at once
            // We do this, because we can do multiple URL requests in parallel, which is faster than doing them one by one
            /** @var \Laravel\Ai\Responses\Data\Citation[] $citations */
            $citations = [];

            foreach ($res as $chunk) {
                switch (true) {
                    case $chunk instanceof Error:
                        $this->logger->error('Error chunk received from agent response', ['chunk' => $chunk]);
                        yield $formatData(content: $chunk->message, type: 'message', isDone: true);
                        break;
                    case $chunk instanceof Citation:
                        $citations[] = $chunk->citation;
                        break;
                    case $chunk instanceof TextDelta:
                        yield $formatData(content: $chunk->delta, type: 'message');
                        break;
                    case $chunk instanceof ReasoningStart:
                        yield $formatStatus('reasoning');
                        break;
                    case $chunk instanceof ReasoningDelta:
                        yield $formatStatus('reasoning_delta', $chunk->delta);
                        break;
                    case $chunk instanceof ProviderToolEvent:
                        yield $formatStatus('provider_tool_call', $chunk->type);
                        break;
                    case $chunk instanceof ToolCall:
                        yield $formatStatus('tool_call', $chunk->name);
                        break;
                    case $chunk instanceof StreamEnd:
                        foreach ($this->citationCleaner->cleanMany($citations) as $cleanedCitation) {
                            yield $formatData(content: $cleanedCitation, type: 'citation');
                        }
                        yield $formatData(content: '', type: 'message');
                        break;
                }
            }

            yield $formatData(content: $res->text, type: 'completion', isDone: true);

        } catch (RequestException $e) {
            $this->logger->error('RequestException while streaming response of agent', [
                'exception' => $e,
                'response' => $e->response->body()
            ]);
            yield $formatError('There was an error while sending your request to the AI agent. Please try again later.');
            return;
        } catch (\Throwable $e) {
            $this->logger->error('Something went wrong in the stream', ['exception' => $e]);
            yield $formatError('There was an error while sending your request to the AI agent. Please try again later.');
            return;
        }

        $this->usageAnalyzer->submitUsageRecord($agent->getUsage(), 'private');
    }

    /**
     * Handle a non-streaming request that is not part of a group chat.
     * This is used for the "export" feature, or any other feature that requires a single response from the AI agent without streaming.
     */
    private function handleNonStreamingRequest(array $validatedData): array
    {
        try {
            $agent = $this->aiService->getAgent($validatedData);
            $res = $agent->send();
        } catch (RequestException $e) {
            $this->logger->error('RequestException while sending request to agent', [
                'exception' => $e,
                'response' => $e->response->body()
            ]);
            return ['success' => false, 'error' => 'There was an error while sending your request to the AI agent. Please try again later.'];
        } catch (\Throwable $e) {
            $this->logger->error('Unexpected error while sending request to agent', ['exception' => $e]);
            return ['success' => false, 'error' => 'There was an unexpected error while processing your request. Please try again later.'];
        }

        // Record usage
        $this->usageAnalyzer->submitUsageRecord($agent->getUsage(), 'private');

        return ['success' => true, 'content' => json_encode(['text' => $res->text], JSON_THROW_ON_ERROR)];
    }

    /**
     * Handle group chat requests with the new architecture
     */
    private function handleGroupChatRequest(array $validatedData, AiModel $model): void
    {
        $isUpdate = (bool)($validatedData['isUpdate'] ?? false);

        // @todo use a repository
        $room = Room::where('slug', $validatedData['slug'])->firstOrFail();

        try {
            // Broadcast initial generation status
            $generationStatus = [
                'type' => 'status',
                'data' => [
                    'slug' => $room->slug,
                    'isGenerating' => true,
                    'model' => $validatedData['payload']['model']
                ]
            ];

            broadcast(new RoomMessageEvent($generationStatus));

            // We set this to true, to tell the request factory to look for the group chat context and not the private context.
            // Ugly as sin, and a real hack, but just a temporary construct until 2.6.0, so go and judge me!
            $validatedData['payload']['broadcast'] = true;
            $agent = $this->aiService->getAgent($validatedData);

            $res = $agent->send();

            $this->usageAnalyzer->submitUsageRecord($agent->getUsage(), 'group');

            $text = $res->text;

            $citations = $this->citationCleaner->cleanMany($res->meta->citations->all());
        } catch (\Throwable $e) {
            $this->logger->error('Error handling group chat request', [
                'exception' => $e,
                'room_slug' => $room->slug,
            ]);

            broadcast(new RoomMessageEvent([
                'type' => 'status',
                'data' => [
                    'slug' => $room->slug,
                    'isGenerating' => false,
                    'error' => 'Failed to generate response. Please try again later.',
                    'model' => $validatedData['payload']['model']
                ]
            ]));

            return;
        }

        $content = [
            'text' => $text
        ];

        if (!empty($citations)) {
            $content['citations'] = array_map(static function (\Laravel\Ai\Responses\Data\Citation $citation): array {
                if ($citation instanceof Arrayable) {
                    return $citation->toArray();
                }
                return [
                    'title' => $citation->title ?? '',
                ];
            }, $citations);
        }

        $encryptedData = (new SymmetricCrypto())->encrypt(
            json_encode($content, JSON_THROW_ON_ERROR),
            base64_decode($validatedData['key'])
        );

        // Store message
        $member = $room->members()->where('user_id', 1)->firstOrFail();
        if ($isUpdate) {
            $message = $this->groupMessageHandler->update($room, [
                'message_id' => $validatedData['messageId'],
                'model' => $validatedData['payload']['model'],
                'content' => [
                    'text' => [
                        'ciphertext' => base64_encode($encryptedData->ciphertext),
                        'iv' => base64_encode($encryptedData->iv),
                        'tag' => base64_encode($encryptedData->tag),
                    ]
                ],
                'metadata' => [
                    'tools' => $validatedData['payload']['tools'] ?? null,
                    'params' => $validatedData['payload']['params'] ?? null,
                ],
            ]);
        } else {
            $message = $this->groupMessageHandler->create(
                $room,
                [
                    'threadId' => $validatedData['threadIndex'],
                    'member' => $member,
                    'message_role' => 'assistant',
                    'model' => $validatedData['payload']['model'],
                    'content' => [
                        'text' => [
                            'ciphertext' => base64_encode($encryptedData->ciphertext),
                            'iv' => base64_encode($encryptedData->iv),
                            'tag' => base64_encode($encryptedData->tag),
                        ]
                    ],
                    'metadata' => [
                        'tools' => $validatedData['payload']['tools'] ?? null,
                        'params' => $validatedData['payload']['params'] ?? null,
                    ],
                ],
                $member->user
            );
        }

        $broadcastObject = [
            'slug' => $room->slug,
            'message_id' => $message->message_id,
        ];

        SendMessage::dispatch($broadcastObject, $isUpdate)->onQueue('message_broadcast');

        RoomAiWritingEndedEvent::dispatch($room, $model);

        // Update and broadcast final generation status
        broadcast(new RoomMessageEvent([
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => false,
                'model' => $validatedData['payload']['model']
            ]
        ]));
    }
}
