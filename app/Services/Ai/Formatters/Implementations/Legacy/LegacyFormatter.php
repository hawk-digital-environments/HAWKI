<?php

declare(strict_types=1);

namespace App\Services\Ai\Formatters\Implementations\Legacy;

use App\Models\User;
use App\Services\Ai\Chat\Values\AiRequest;
use App\Services\Ai\Chat\Values\AiResponse;
use App\Services\Ai\Chat\Values\Configs\StreamConfig;
use App\Services\Ai\Chat\Values\Messages\AssistantMessage;
use App\Services\Ai\Chat\Values\Messages\UserMessage;
use App\Services\Ai\Chat\Values\Parts\FilePart;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Stream\AiStreamEvent;
use App\Services\Ai\Formatters\Contracts\FormatterInterface;
use App\Services\Ai\Formatters\Exceptions\FormatterRequestException;
use App\Services\Ai\Formatters\Exceptions\InvalidInputItemException;
use App\Services\Ai\Formatters\Exceptions\InvalidRequestBodyException;
use App\Services\Storage\AvatarStorageService;
use App\Services\Users\Repositories\UserRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The legacy HAWKI NDJSON wire format (format key `legacy`) — the dialect the legacy
 * frontend (`public/js`) speaks on the private-chat and `ai-req` routes.
 *
 * Wire format only: the group-chat orchestration (broadcast, encryption, persistence)
 * is **not** a formatter concern and stays on {@see \App\Http\Controllers\StreamController}
 * until the Phase-5 refactor. Per-message attachment UUIDs ride the IR as
 * `hawki-storage://` file parts, which {@see \App\Services\Ai\Chat\Factories\Implementations\ChatAgentFactory}
 * resolves through HAWKI's file storage.
 */
readonly class LegacyFormatter implements FormatterInterface
{
    public const string KEY = 'legacy';

    public function __construct(
        private UserRepository $userRepository,
        private AvatarStorageService $avatarStorage,
    ) {
    }

    public function getKey(): string
    {
        return self::KEY;
    }

    public function parseRequest(Request $request): AiRequest
    {
        $body = $request->json()->all();

        if (!\is_array($body) || [] === $body || !\is_array($body['payload'] ?? null)) {
            throw InvalidRequestBodyException::forUnparseableBody();
        }

        return $this->parsePayload($body);
    }

    /**
     * Parses an already-decoded legacy wire body (the full request array, including the
     * top-level `broadcast`/`slug` fields). Used by the group-chat orchestration, which
     * receives the payload as a validated array rather than an HTTP request.
     */
    public function parsePayload(array $body): AiRequest
    {
        if ([] === $body || !\is_array($body['payload'] ?? null)) {
            throw InvalidRequestBodyException::forUnparseableBody();
        }

        $payload = $body['payload'];
        $messagesInput = $payload['messages'] ?? null;

        if (!\is_array($messagesInput) || [] === $messagesInput) {
            throw InvalidRequestBodyException::forMissingInput();
        }

        $model = $payload['model'] ?? null;

        if (!\is_string($model) || '' === $model) {
            throw new InvalidInputItemException(
                'The request is missing the required "payload.model" field.',
                errorCode: 'missing_model',
                param: 'payload.model',
            );
        }

        $messages = [];
        $systemParts = [];

        foreach ($messagesInput as $message) {
            $this->parseMessageItem($message, $messages, $systemParts);
        }

        $lastMessage = $messages[array_key_last($messages)] ?? null;

        if (!$lastMessage instanceof UserMessage) {
            throw InvalidInputItemException::forMissingTrailingUserMessage();
        }

        $hawkiExtensions = [];

        if (\is_array($payload['tools'] ?? null)) {
            $hawkiExtensions[AiRequest::HAWKI_EXTENSION_TOOLS] = array_values(array_filter(
                $payload['tools'],
                static fn (mixed $tool): bool => \is_string($tool),
            ));
        }

        if (\is_array($payload['params'] ?? null)) {
            $hawkiExtensions[AiRequest::HAWKI_EXTENSION_PARAMS] = $payload['params'];
        }

        if (true === ($body['broadcast'] ?? false)) {
            $hawkiExtensions[AiRequest::HAWKI_EXTENSION_BROADCAST] = true;
        }

        return new AiRequest(
            model: $model,
            messages: $messages,
            systemInstruction: [] !== $systemParts ? $systemParts : null,
            stream: new StreamConfig(enabled: true === ($payload['stream'] ?? false)),
            hawkiExtensions: [] !== $hawkiExtensions ? $hawkiExtensions : null,
            formatKey: self::KEY,
        );
    }

    public function formatResponse(AiResponse $response): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'content' => json_encode(['text' => $response->message->text()], \JSON_THROW_ON_ERROR),
        ]);
    }

    public function formatStream(iterable $events): StreamedResponse
    {
        $hawki = $this->userRepository->findHawki();

        $context = new LegacyStreamContext(
            author: [
                'username' => $hawki->username,
                'name' => $hawki->name,
                'avatar_url' => $this->avatarStorage->retrieveAvatar($hawki)?->getUrl(),
            ],
        );

        return response()->stream(
            static function () use ($events, $context): void {
                $emit = static function (array $frame): void {
                    echo json_encode($frame, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE) . "\n";
                    flush();
                };

                try {
                    foreach ($events as $event) {
                        foreach ($context->transform($event) as $frame) {
                            $emit($frame);
                        }
                    }
                } catch (\Throwable $e) {
                    $emit($context->error($e));

                    return;
                }

                foreach ($context->end() as $frame) {
                    $emit($frame);
                }
            },
            200,
            $this->getStreamHeaders(),
        );
    }

    /**
     * @return array<string, string>
     */
    public function getStreamHeaders(): array
    {
        return [
            // Kept byte-compatible with the legacy StreamController response, whose
            // NDJSON stream (mis)labels itself as text/event-stream.
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'Access-Control-Allow-Origin' => '*',
        ];
    }

    public function formatError(FormatterRequestException $exception): Response
    {
        return new JsonResponse([
            'success' => false,
            'message' => $exception->getMessage(),
        ], $exception->httpStatus());
    }

    /**
     * @param array<int, \App\Services\Ai\Chat\Values\Messages\Message>  $messages
     * @param array<int, TextPart>                                       $systemParts
     */
    private function parseMessageItem(mixed $item, array &$messages, array &$systemParts): void
    {
        if (!\is_array($item) || !\is_string($item['role'] ?? null)) {
            throw InvalidInputItemException::forMalformedItem($item);
        }

        $role = $item['role'];
        $content = \is_array($item['content'] ?? null) ? $item['content'] : [];
        $text = \is_string($content['text'] ?? null) ? $content['text'] : '';

        if ('system' === $role) {
            if ('' !== $text) {
                $systemParts[] = TextPart::from($text);
            }

            return;
        }

        if ('user' === $role) {
            $parts = [TextPart::from($text)];

            foreach (\is_array($content['attachments'] ?? null) ? $content['attachments'] : [] as $uuid) {
                if (\is_string($uuid) && '' !== $uuid) {
                    $parts[] = new FilePart(fileUrl: AiRequest::HAWKI_STORAGE_SCHEME . $uuid);
                }
            }

            $messages[] = new UserMessage(parts: $parts);

            return;
        }

        if ('assistant' === $role) {
            $messages[] = new AssistantMessage(parts: [TextPart::from($text)]);

            return;
        }

        throw InvalidInputItemException::forUnknownType($role);
    }
}
