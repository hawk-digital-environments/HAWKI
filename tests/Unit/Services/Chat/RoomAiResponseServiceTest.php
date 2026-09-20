<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Chat;

use App\Jobs\SendMessage;
use App\Models\Member;
use App\Models\Message;
use App\Models\Room;
use App\Models\User;
use App\Services\Ai\Chat\ChatService;
use App\Events\RoomMessageEvent;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\FinishReason;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Parts\CitationPart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Parts\UrlCitation;
use App\Services\Chat\Events\RoomAiWritingEndedEvent;
use App\Services\Chat\Events\RoomAiWritingStartedEvent;
use App\Services\Ai\Formatters\Implementations\Legacy\LegacyFormatter;
use App\Services\Chat\RoomAiResponseService;
use Hawk\HawkiCrypto\SymmetricCrypto;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RoomAiResponseService::class)]
class RoomAiResponseServiceTest extends TestCase
{
    use RefreshDatabase;

    private Room $room;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        // HAWKI member authors the AI messages (findHawki() resolves user id 1)
        if (null === User::query()->withoutGlobalScopes()->find(1)) {
            User::forceCreate([
                'id' => 1,
                'name' => 'HAWKI',
                'email' => 'HAWKI@hawk.de',
                'username' => 'HAWKI',
                'employeetype' => 'system',
                'publicKey' => str_repeat('h', 64),
                'isRemoved' => false,
            ]);
        }

        $this->room = Room::forceCreate([
            'room_name' => 'Test room',
            'slug' => 'test-room',
        ]);
        Member::forceCreate([
            'room_id' => $this->room->id,
            'user_id' => 1,
            'role' => Member::ROLE_ASSISTANT,
        ]);

        $this->key = base64_encode(random_bytes(32));
    }

    public function testItGeneratesPersistsAndBroadcasts(): void
    {
        Queue::fake([SendMessage::class]);
        Event::fake([RoomMessageEvent::class, RoomAiWritingStartedEvent::class, RoomAiWritingEndedEvent::class]);
        $this->swapChatServiceWithResponse(new AiResponse(
            id: 'resp_1',
            model: 'gpt-4o',
            created: 1,
            message: new AssistantMessage(parts: [
                TextPart::from('Hello group!'),
                new CitationPart(urlCitation: new UrlCitation(
                    startIndex: 0,
                    endIndex: 5,
                    title: 'Source',
                    url: 'https://example.com/source',
                )),
            ]),
            finishReason: FinishReason::stop(),
        ));

        $this->generate($this->payload());

        $message = Message::query()->where('room_id', $this->room->id)->first();
        self::assertNotNull($message);
        self::assertSame('assistant', $message->message_role);
        self::assertSame(1, $message->member->user_id);

        // encrypted content decrypts with the room key into text + citation wire shape
        $decrypted = (new SymmetricCrypto())->decrypt(
            new \Hawk\HawkiCrypto\Value\SymmetricCryptoValue(
                base64_decode($message->iv),
                base64_decode($message->tag),
                base64_decode($message->content),
            ),
            base64_decode($this->key),
        );
        $content = json_decode($decrypted, true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('Hello group!', $content['text']);
        self::assertSame([
            [
                'url' => 'https://example.com/source',
                'title' => 'Source',
                'start_index' => 0,
                'end_index' => 5,
            ],
        ], $content['citations']);

        Queue::assertPushedOn('message_broadcast', SendMessage::class);
        Event::assertDispatched(RoomAiWritingEndedEvent::class);
        Event::assertDispatched(RoomMessageEvent::class, fn (RoomMessageEvent $event) => false === $event->data['data']['isGenerating']);
    }

    public function testItUpdatesTheMessageWhenRegenerating(): void
    {
        Queue::fake([SendMessage::class]);
        Event::fake([RoomMessageEvent::class, RoomAiWritingStartedEvent::class, RoomAiWritingEndedEvent::class]);
        $existing = Message::forceCreate([
            'room_id' => $this->room->id,
            'member_id' => $this->room->members()->where('user_id', 1)->first()->id,
            'message_id' => '1.001',
            'message_role' => 'assistant',
            'thread_id' => null,
            'model' => 'gpt-4o',
            'iv' => base64_encode('iv'),
            'tag' => base64_encode('tag'),
            'content' => base64_encode('old'),
            'metadata' => [],
        ]);
        $this->swapChatServiceWithResponse($this->textResponse('Regenerated!'));

        $this->generate($this->payload(isUpdate: true, messageId: '1.001'));

        self::assertSame(1, Message::query()->where('room_id', $this->room->id)->count());
        $message = Message::query()->where('room_id', $this->room->id)->first();
        self::assertSame($existing->id, $message->id);
        self::assertNotSame(base64_encode('old'), $message->content);

        Queue::assertPushedOn('message_broadcast', SendMessage::class);
    }

    public function testItBroadcastsErrorStatusWhenGenerationFails(): void
    {
        Queue::fake([SendMessage::class]);
        Event::fake([RoomMessageEvent::class, RoomAiWritingStartedEvent::class, RoomAiWritingEndedEvent::class]);
        $this->swapChatService(fn () => throw new \RuntimeException('provider down'));

        $this->generate($this->payload());

        self::assertSame(0, Message::query()->where('room_id', $this->room->id)->count());
        Queue::assertNotPushed(SendMessage::class);
        Event::assertDispatched(RoomMessageEvent::class, fn (RoomMessageEvent $event) => false === $event->data['data']['isGenerating']
            && 'Failed to generate response. Please try again later.' === ($event->data['data']['error'] ?? null));
        Event::assertDispatched(RoomAiWritingEndedEvent::class);
    }

    private function sut(): RoomAiResponseService
    {
        return $this->app->make(RoomAiResponseService::class);
    }

    private function generate(array $payload): void
    {
        $this->sut()->generate(
            $this->room,
            $this->aiModel(),
            $this->app->make(LegacyFormatter::class)->parsePayload($payload),
            [
                'threadIndex' => $payload['threadIndex'] ?? 0,
                'messageId' => $payload['messageId'] ?? null,
                'isUpdate' => (bool)($payload['isUpdate'] ?? false),
                'key' => $payload['key'] ?? null,
            ],
        );
    }

    private function aiModel(): \App\Models\Ai\AiModel
    {
        $model = new \App\Models\Ai\AiModel(['model_id' => 'gpt-4o']);
        $model->exists = true;
        $model->id = 1;

        return $model;
    }

    private function payload(bool $isUpdate = false, ?string $messageId = null): array
    {
        return [
            'broadcast' => true,
            'threadIndex' => 0,
            'slug' => $this->room->slug,
            'isUpdate' => $isUpdate,
            'messageId' => $messageId,
            'key' => $this->key,
            'payload' => [
                'model' => 'gpt-4o',
                'broadcast' => true,
                'stream' => false,
                'messages' => [
                    ['role' => 'user', 'content' => ['text' => 'Hi there']],
                ],
                'tools' => null,
                'params' => null,
            ],
        ];
    }

    private function textResponse(string $text): AiResponse
    {
        return new AiResponse(
            id: 'resp_1',
            model: 'gpt-4o',
            created: 1,
            message: AssistantMessage::fromText($text),
            finishReason: FinishReason::stop(),
        );
    }

    private function swapChatServiceWithResponse(AiResponse $response): void
    {
        $this->swapChatService(fn (): AiResponse => $response);
    }

    private function swapChatService(\Closure $send): void
    {
        $this->app->instance(ChatService::class, new readonly class ($send) extends ChatService {
            public function __construct(private readonly \Closure $send)
            {
            }

            public function send(\App\Services\Ai\Chat\Values\AiRequest $request): AiResponse
            {
                return ($this->send)();
            }
        });
    }
}
