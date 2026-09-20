<?php

declare(strict_types=1);

namespace App\Services\Ai\Chat\Values;

use App\Services\Ai\Chat\Values\Configs\CacheConfig;
use App\Services\Ai\Chat\Values\Configs\GenerationConfig;
use App\Services\Ai\Chat\Values\Configs\ReasoningConfig;
use App\Services\Ai\Chat\Values\Configs\ResponseFormatConfig;
use App\Services\Ai\Chat\Values\Configs\StreamConfig;
use App\Services\Ai\Chat\Values\Messages\Message;
use App\Services\Ai\Chat\Values\Parts\TextPart;
use App\Services\Ai\Chat\Values\Tools\ToolCallConfig;
use App\Services\Ai\Chat\Values\Tools\ToolChoice;
use App\Services\Ai\Chat\Values\Tools\ToolDefinition;

/**
 * The provider-neutral chat request IR — the hub of the spoke-and-hub architecture.
 *
 * Downstream formatters parse their wire format into this representation; the agent
 * factory chain consumes it to wire a {@see \App\Services\Ai\Agents\Contracts\AgentInterface}.
 *
 * Anything a wire format expresses that the IR does not model natively rides in
 * {@see $providerExtensions} (provider-facing escape hatch) or {@see $hawkiExtensions}
 * (HAWKI-specific request metadata such as tool-transfer strings, attachment UUIDs, or
 * assistant routing handles).
 */
readonly class AiRequest
{
    public const string HAWKI_EXTENSION_TOOLS = 'tools';
    public const string HAWKI_EXTENSION_ATTACHMENTS = 'attachments';
    public const string HAWKI_EXTENSION_PARAMS = 'params';
    public const string HAWKI_EXTENSION_BROADCAST = 'broadcast';

    /**
     * Numeric room id for room-scoped requests (group chat orchestration). Orchestration
     * callers that already resolved the room set this; the agent factory copies it onto
     * the {@see \App\Services\Ai\Agents\Values\AgentRequestContext} so usage recording
     * can attribute the invocation to the room.
     */
    public const string HAWKI_EXTENSION_ROOM_ID = 'room_id';

    /**
     * Entry-point channel ('chat' / 'ui-chat' / …) for usage attribution; flows onto the
     * usage record and {@see \App\Services\Ai\Chat\Events\UsageRecordedEvent}.
     */
    public const string HAWKI_EXTENSION_CHANNEL = 'channel';

    /**
     * IR convention for HAWKI-stored files referenced from file parts: the file URL
     * carries this scheme plus the storage UUID, resolved by the agent factory through
     * HAWKI's file storage (per-request storage category).
     */
    public const string HAWKI_STORAGE_SCHEME = 'hawki-storage://';

    public function __construct(
        /**
         * Model slug as requested by the client; null resolves to the system default chat model.
         */
        public ?string $model = null,
        /**
         * @var array<int, Message> conversation history in chronological order
         */
        public array $messages = [],
        /**
         * @var null|array<int, TextPart> system instructions, when the wire format separates them
         */
        public ?array $systemInstruction = null,
        /**
         * @var null|array<int, ToolDefinition>
         */
        public ?array $tools = null,
        public ?ToolChoice $toolChoice = null,
        public ?ToolCallConfig $toolConfig = null,
        public ?GenerationConfig $generation = null,
        public ?ResponseFormatConfig $responseFormat = null,
        public ?StreamConfig $stream = null,
        public ?ReasoningConfig $reasoning = null,
        public ?CacheConfig $cache = null,
        /**
         * @var null|array<string, mixed> opaque provider-facing parameters, tagged by the capturing formatter
         */
        public ?array $providerExtensions = null,
        /**
         * @var null|array<string, mixed> HAWKI-specific request metadata (tools, attachments, params, …)
         */
        public ?array $hawkiExtensions = null,
        /**
         * Key of the formatter that produced this request (e.g. 'openResponses').
         */
        public ?string $formatKey = null,
    ) {
    }

    /**
     * Returns the concatenated system-instruction text, or null when absent.
     */
    public function systemInstructionText(): ?string
    {
        if (null === $this->systemInstruction || [] === $this->systemInstruction) {
            return null;
        }

        return implode("\n\n", array_map(static fn (TextPart $part): string => $part->text, $this->systemInstruction));
    }

    /**
     * Returns a hawki extension entry, or null when not present.
     */
    public function hawkiExtension(string $key): mixed
    {
        return $this->hawkiExtensions[$key] ?? null;
    }

    /**
     * Returns a clone with the given hawki extension set. Used by orchestration callers
     * (e.g. the group-chat orchestration) that attach server-side context — room id,
     * channel — after the wire payload was parsed.
     */
    public function withHawkiExtension(string $key, mixed $value): self
    {
        $extensions = $this->hawkiExtensions ?? [];
        $extensions[$key] = $value;

        return new self(
            model: $this->model,
            messages: $this->messages,
            systemInstruction: $this->systemInstruction,
            tools: $this->tools,
            toolChoice: $this->toolChoice,
            toolConfig: $this->toolConfig,
            generation: $this->generation,
            responseFormat: $this->responseFormat,
            stream: $this->stream,
            reasoning: $this->reasoning,
            cache: $this->cache,
            providerExtensions: $this->providerExtensions,
            hawkiExtensions: $extensions,
            formatKey: $this->formatKey,
        );
    }

    public function wantsStreaming(): bool
    {
        return null !== $this->stream && $this->stream->enabled;
    }
}
