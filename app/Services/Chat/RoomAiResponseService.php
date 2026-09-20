<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Jobs\SendMessage;
use App\Models\Ai\AiModel;
use App\Models\Room;
use App\Events\RoomMessageEvent;
use App\Models\Message;
use App\Services\Ai\Chat\ChatService;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use App\Services\Chat\Events\RoomAiWritingEndedEvent;
use App\Services\Chat\Message\Handlers\GroupMessageHandler;
use App\Services\Users\Repositories\UserRepository;
use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Psr\Log\LoggerInterface;

/**
 * Group-chat AI orchestration: generates the response for a room request through the
 * chat proxy service and delivers it into the room — encrypted with the room's
 * client-supplied key, persisted via the {@see GroupMessageHandler}, and broadcast to
 * every member through Reverb.
 *
 * This is the shared core behind both the legacy route
 * (`POST /req/room/streamAI/{slug}`, still spoken by the legacy frontend) and the
 * native endpoint (`POST /api/hawki/v1/ui-chat/{slug}`). It runs in queue context
 * ({@see \App\Jobs\GenerateRoomAiResponse}), so it must not rely on the authenticated
 * user — the AI response is authored by the HAWKI member.
 */
readonly class RoomAiResponseService
{
    public function __construct(
        private ChatService $chatService,
        private LegacyFormatter $legacyFormatter,
        private GroupMessageHandler $groupMessageHandler,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @param array $validatedPayload the validated legacy wire payload (top-level
     *                                `broadcast`/`threadIndex`/`messageId`/`isUpdate`/`key`
     *                                fields plus the `payload` object)
     */
    public function generate(Room $room, AiModel $model, array $validatedPayload): void
    {
        $this->broadcastGenerationStatus($room, $validatedPayload['payload']['model'], true);

        try {
            $aiRequest = $this->legacyFormatter
                ->parsePayload($validatedPayload)
                ->withHawkiExtension(AiRequest::HAWKI_EXTENSION_CHANNEL, 'ui-chat')
                ->withHawkiExtension(AiRequest::HAWKI_EXTENSION_ROOM_ID, $room->id);

            $response = $this->chatService->send($aiRequest);

            $content = [
                'text' => $response->message->text(),
            ];

            $citations = $this->citationsFrom($response->message->parts);
            if ([] !== $citations) {
                $content['citations'] = $citations;
            }

            $encrypted = (new SymmetricCrypto())->encrypt(
                json_encode($content, JSON_THROW_ON_ERROR),
                base64_decode((string)($validatedPayload['key'] ?? '')),
            );

            $message = $this->persistAiMessage($room, $validatedPayload, $encrypted);
        } catch (\Throwable $e) {
            $this->logger->error('Error handling group chat request', [
                'exception' => $e,
                'room_slug' => $room->slug,
            ]);

            $this->broadcastGenerationStatus(
                $room,
                $validatedPayload['payload']['model'],
                false,
                'Failed to generate response. Please try again later.',
            );
            RoomAiWritingEndedEvent::dispatch($room, $model);

            return;
        }

        SendMessage::dispatch([
            'slug' => $room->slug,
            'message_id' => $message->message_id,
        ], (bool)($validatedPayload['isUpdate'] ?? false))->onQueue('message_broadcast');

        RoomAiWritingEndedEvent::dispatch($room, $model);
        $this->broadcastGenerationStatus($room, $validatedPayload['payload']['model'], false);
    }

    /**
     * Serializes the cleaned URL citations attached to the response into the wire shape
     * the room clients decrypt: url / title / start_index / end_index (identical to the
     * vendor citation serialization this path replaced).
     *
     * @param array<int, mixed> $parts
     * @return array<int, array{url: string|null, title: string|null, start_index: int|null, end_index: int|null}>
     */
    private function citationsFrom(array $parts): array
    {
        $citations = [];

        foreach ($parts as $part) {
            if ($part instanceof CitationPart && null !== $part->urlCitation) {
                $citations[] = [
                    'url' => $part->urlCitation->url,
                    'title' => $part->urlCitation->title,
                    'start_index' => $part->urlCitation->startIndex,
                    'end_index' => $part->urlCitation->endIndex,
                ];
            }
        }

        return $citations;
    }

    /**
     * Persists the encrypted AI response as a HAWKI-authored room message — either as a
     * new message or as an in-place update of the regenerated one.
     */
    private function persistAiMessage(Room $room, array $validatedPayload, SymmetricCryptoValue $encrypted): Message
    {
        $hawki = $this->userRepository->findHawki();
        $content = [
            'text' => [
                'ciphertext' => base64_encode($encrypted->ciphertext),
                'iv' => base64_encode($encrypted->iv),
                'tag' => base64_encode($encrypted->tag),
            ],
        ];
        $metadata = [
            'tools' => $validatedPayload['payload']['tools'] ?? null,
            'params' => $validatedPayload['payload']['params'] ?? null,
        ];

        if ($validatedPayload['isUpdate'] ?? false) {
            return $this->groupMessageHandler->update($room, [
                'message_id' => $validatedPayload['messageId'],
                'model' => $validatedPayload['payload']['model'],
                'content' => $content,
                'metadata' => $metadata,
            ]);
        }

        $member = $room->members()->where('user_id', $hawki->id)->firstOrFail();

        return $this->groupMessageHandler->create(
            $room,
            [
                'threadId' => $validatedPayload['threadIndex'] ?? null,
                'member' => $member,
                'message_role' => 'assistant',
                'model' => $validatedPayload['payload']['model'],
                'content' => $content,
                'metadata' => $metadata,
            ],
            $hawki,
        );
    }

    private function broadcastGenerationStatus(Room $room, string $model, bool $isGenerating, ?string $error = null): void
    {
        broadcast(new RoomMessageEvent([
            'type' => 'status',
            'data' => [
                'slug' => $room->slug,
                'isGenerating' => $isGenerating,
                'model' => $model,
                ...(null === $error ? [] : ['error' => $error]),
            ],
        ]));
    }
}
