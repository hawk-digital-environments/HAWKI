<?php

declare(strict_types=1);

namespace App\Services\Ai\Agents\Implementations\Chat;

use App\Models\Assistants\Assistant;
use App\Models\User;
use App\Services\Ai\Agents\Contracts\AgentFactoryInterface;
use App\Services\Ai\Agents\Contracts\AgentInterface;
use App\Services\Assistant\AssistantRunComposer;
use App\Services\Assistant\Repositories\AssistantRepository;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Psr\Log\LoggerInterface;

/**
 * Agent factory that claims legacy-shaped chat requests carrying an explicit
 * `payload.assistant_handle`, delegating the actual agent creation to
 * {@see ChatAgentFromLegacyRequestFactory} with an assistant-assembled
 * payload.
 *
 * The handle is the sole detection mechanism — the assistant's `@handle`
 * inside the message text is addressing chrome, never a trigger. Senders of
 * the field: the new frontend's native chat transport and the legacy bridge
 * (private chat + rooms, mapped through `buildRequestObject`), plus the
 * OpenAI responses endpoint via {@see \App\Services\Ai\Streaming\AgentStreamer}.
 * The server-side streamer clients pre-compose their exchange, so the
 * factory simply re-assembles from the assistant — one authoritative
 * composition source ({@see AssistantRunComposer}).
 *
 * The rewritten payload carries the composed system prompt, the assistant's
 * model per its `allow_model_select` policy, its sampling parameters merged
 * over the client's, its tools merged with the client's, and the assistant's
 * `@handle` token stripped from the user messages — it is addressing
 * metadata, not conversation content.
 *
 * Requests without the field — or referencing an assistant the acting user
 * may not view — are declined (null) so the plain
 * {@see ChatAgentFromLegacyRequestFactory} handles them unchanged.
 */
#[Singleton]
class AssistantChatAgentFactory implements AgentFactoryInterface
{
    public function __construct(
        private readonly ChatAgentFromLegacyRequestFactory $chatFactory,
        private readonly AssistantRepository $assistantRepository,
        private readonly AssistantRunComposer $runComposer,
        private readonly AuthFactory $auth,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Returns an assistant-driven {@see ChatAgent} when the request carries
     * an explicit `assistant_handle` referencing a visible assistant, null
     * otherwise.
     */
    public function createAgent(mixed $request): AgentInterface|null
    {
        $payload = LegacyChatRequestPayload::tryFromRequest($request);
        if ($payload === null) {
            return null;
        }

        $handle = $payload->assistantHandle();
        if ($handle === null) {
            return null;
        }

        $actor = $this->currentUser();
        $assistant = $this->assistantRepository->findOneByHandle($handle);

        if ($assistant === null) {
            return null;
        }

        if (!$this->isVisibleTo($assistant, $actor)) {
            $this->logger->warning(
                'The explicitly requested assistant is not visible to the acting user; the request falls back to a plain chat run.',
                ['assistant_handle' => $handle],
            );

            return null;
        }

        return $this->chatFactory->createAgent(
            $this->rewriteRequest($payload, $assistant, $actor),
        );
    }

    /**
     * Rewrites the legacy payload for the delegated chat factory: the
     * assistant-composed instructions replace the first system message (one
     * is prepended when absent), the model follows the assistant's selection
     * policy, its sampling parameters merge over the client's, its tools
     * merge with the client's, and the mention token is stripped.
     *
     * @return array{payload: array<string, mixed>}
     */
    private function rewriteRequest(LegacyChatRequestPayload $payload, Assistant $assistant, ?User $actor): array
    {
        $inner = $payload->toArray();
        $run = $this->runComposer->compose($assistant, $actor);

        $inner['model'] = $this->resolveModelId($payload->modelId(), $assistant);
        $inner['messages'] = $this->stripHandleFromUserMessages(
            $this->applySystemInstructions($payload->messages(), $run->systemPrompt),
            $assistant->handle,
        );
        $inner['params'] = array_merge($payload->params(), $run->params);
        $inner['tools'] = array_values(array_unique([
            ...$run->toolTransferStrings,
            ...$payload->tools(),
        ]));

        unset($inner['assistant_handle']);

        return ['payload' => $inner];
    }

    /**
     * The client-requested model only wins when the assistant allows model
     * selection; otherwise the assistant's own model is used.
     */
    private function resolveModelId(string $payloadModelId, Assistant $assistant): string
    {
        if ($payloadModelId !== '' && $assistant->allow_model_select) {
            return $payloadModelId;
        }

        return $assistant->model;
    }

    /**
     * Replaces the text of the first system message with the composed
     * instructions, prepending one when the payload carries no system
     * message.
     *
     * @param list<array<string, mixed>> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function applySystemInstructions(array $messages, string $systemPrompt): array
    {
        foreach ($messages as $index => $message) {
            if (($message['role'] ?? null) === 'system') {
                $messages[$index]['content']['text'] = $systemPrompt;

                return $messages;
            }
        }

        array_unshift($messages, ['role' => 'system', 'content' => ['text' => $systemPrompt]]);

        return $messages;
    }

    /**
     * Removes the assistant's `@handle` token from every user message —
     * the token is addressing chrome the composer leaves in the text.
     *
     * @param list<array<string, mixed>> $messages
     *
     * @return list<array<string, mixed>>
     */
    private function stripHandleFromUserMessages(array $messages, string $handle): array
    {
        if ($handle === '') {
            return $messages;
        }

        $pattern = '/\s*@' . preg_quote($handle, '/') . '(?=\s|$)/';

        foreach ($messages as $index => $message) {
            if (($message['role'] ?? null) !== 'user') {
                continue;
            }

            $text = $message['content']['text'] ?? null;

            if (\is_string($text) && preg_match($pattern, $text) === 1) {
                $messages[$index]['content']['text'] = trim((string) preg_replace($pattern, '', $text));
            }
        }

        return $messages;
    }

    private function isVisibleTo(Assistant $assistant, ?User $actor): bool
    {
        return $actor !== null && $this->assistantRepository->isVisibleTo($assistant, $actor);
    }

    /**
     * The acting user of the current request. Resolved lazily (and null-safe)
     * because group chat agents are built inside a shutdown function, where
     * the guard may no longer be able to re-authenticate — the then-cached
     * user is still returned.
     */
    private function currentUser(): ?User
    {
        $user = $this->auth->guard()->user();

        return $user instanceof User ? $user : null;
    }
}
