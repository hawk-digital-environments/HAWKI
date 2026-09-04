<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Implementations\Chat;


/**
 * Typed wrapper around the legacy frontend chat request payload.
 *
 * The legacy format is a plain array with the shape:
 * ```php
 * [
 *     'payload' => [
 *         'model'     => 'gpt-4o',           // required: model slug
 *         'messages'  => [                    // required
 *             ['role' => 'system',    'content' => ['text' => '...']],  // system instructions (optional)
 *             ['role' => 'user',      'content' => ['text' => '...', 'attachments' => ['uuid1']]],
 *             ['role' => 'assistant', 'content' => ['text' => '...']],
 *             // ... more turns ...
 *         ],
 *         'params'    => ['temp' => 0.7, 'top_p' => 1.0, 'max_tokens' => 2048],  // optional
 *         'tools'     => ['capability:web_search:auto'],                           // optional
 *         'broadcast' => false,               // optional: true → group storage for attachments
 *     ],
 * ]
 * ```
 *
 * All raw array access on the legacy format lives here; consumers (e.g.
 * {@see ChatAgentFromLegacyRequestFactory}) only see typed accessors. A system
 * prompt is optional: {@see systemInstructions()} returns an empty string when
 * no system message is present or its text is null/absent — an empty system
 * prompt is valid, downstream the HKI_META preamble is always prepended via
 * {@see \App\Services\Ai\Agents\Utils\MessageMetaBlocks::wrapInstructions()}.
 */
readonly class LegacyChatRequestPayload
{
    private function __construct(
        /**
         * The validated inner `payload` array of the legacy request.
         *
         * @var array<string, mixed>
         */
        private array $payload
    )
    {
    }

    /**
     * Returns a payload instance when the request matches the legacy shape, null otherwise.
     * Returning null lets {@see \App\Services\Ai\Agents\AgentRegistry} try the next factory.
     */
    public static function tryFromRequest(mixed $request): self|null
    {
        if (
            !is_array($request)
            || !is_array($request['payload'] ?? null)
            || !is_array($request['payload']['messages'] ?? null)
            || !is_string($request['payload']['model'] ?? null)
        ) {
            return null;
        }

        return new self($request['payload']);
    }

    /**
     * The model slug the request is addressed to.
     */
    public function modelId(): string
    {
        return $this->payload['model'];
    }

    /**
     * The text of the first system message. Returns an empty string when the payload
     * contains no system message or its text is null/absent.
     */
    public function systemInstructions(): string
    {
        foreach ($this->messages() as $message) {
            if (isset($message['role']) && $message['role'] === 'system' && isset($message['content'])) {
                return (string)($message['content']['text'] ?? '');
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function messages(): array
    {
        return $this->payload['messages'];
    }

    /**
     * @return array<string, mixed>
     */
    public function params(): array
    {
        $params = $this->payload['params'] ?? null;
        return is_array($params) ? $params : [];
    }

    /**
     * @return list<mixed>
     */
    public function tools(): array
    {
        $tools = $this->payload['tools'] ?? null;
        return is_array($tools) ? $tools : [];
    }

    /**
     * The explicitly requested assistant handle (`payload.assistant_handle`),
     * or null when absent. Accepted by the stream validation rules, so both
     * the chat clients and the server-side streamer
     * ({@see \App\Services\Ai\Streaming\AgentStreamer}) may set it.
     */
    public function assistantHandle(): string|null
    {
        $handle = $this->payload['assistant_handle'] ?? null;

        return \is_string($handle) && $handle !== '' ? $handle : null;
    }

    /**
     * Whether attachments must be resolved from group storage instead of private storage.
     */
    public function isBroadcast(): bool
    {
        return ($this->payload['broadcast'] ?? null) === true;
    }

    /**
     * The raw payload array, e.g. for exception context.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->payload;
    }
}
