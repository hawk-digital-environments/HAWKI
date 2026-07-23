<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Utils;


use Illuminate\Contracts\Support\Arrayable;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;

/**
 * Accumulates chat turn messages and normalises them into a strictly alternating
 * user / assistant sequence before handing them to an agent.
 *
 * Most AI providers require that the message history strictly alternates between user and
 * assistant roles. The HAWKI frontend can produce sequences that violate this — for example
 * a user sending two messages in a row before the model responds, or an assistant producing
 * two consecutive responses. Inserting an empty placeholder message for the missing role
 * caused context issues in testing, so instead consecutive messages with the same role are
 * merged into a single message using a visible `[[MESSAGE BOUNDARY]]` separator.
 *
 * Example transformations:
 * - `user, user, assistant, user` → `user (merged), assistant, user`
 * - `user, assistant, assistant, user` → `user, assistant (merged), user`
 *
 * Merged messages include a {@see MessageMetaBlocks} preamble that tells the model about
 * the boundary so it can treat them as distinct turns in its reasoning.
 *
 * Usage:
 * ```php
 * $history = new AlternatingMessageHistory();
 * $history->registerUserMessage('First question', $attachments);
 * $history->registerUserMessage('Follow-up question');
 * $history->registerAiMessage('Answer');
 *
 * $messages = $history->toArray(); // [UserMessage(merged), Message(assistant)]
 * ```
 */
class AlternatingMessageHistory implements \IteratorAggregate, Arrayable
{
    /**
     * @var Message[]
     */
    private array $messages = [];

    /**
     * Appends an assistant turn to the history.
     */
    public function registerAiMessage(string $content): self
    {
        $this->messages[] = new Message(
            role: MessageRole::Assistant,
            content: $content
        );

        return $this;
    }

    /**
     * Appends a user turn to the history and optionally applies file attachments and their
     * associated metadata blocks to the resulting {@see UserMessage}.
     */
    public function registerUserMessage(string $content, UserMessageAttachments|null $attachments = null): self
    {
        $message = new UserMessage(
            content: $content
        );
        $attachments?->apply($message);
        $this->messages[] = $message;
        return $this;
    }

    /**
     * Yields the normalised, alternating sequence of messages.
     *
     * Consecutive messages with the same role are merged into a single message via
     * {@see mergeMessages()} before being yielded, guaranteeing strict user/assistant
     * alternation. Returns an empty traversable when no messages have been registered.
     */
    public function build(): \Traversable
    {
        if (empty($this->messages)) {
            return [];
        }

        $firstMessage = $this->messages[0];
        $messagesOfSameRole = [];
        $currentRole = $firstMessage->role;
        foreach ($this->messages as $message) {
            if ($message->role === $currentRole) {
                $messagesOfSameRole[] = $message;
            } else {
                yield $this->mergeMessages($currentRole, $messagesOfSameRole);
                $messagesOfSameRole = [$message];
                $currentRole = $message->role;
            }
        }
        yield $this->mergeMessages($currentRole, $messagesOfSameRole);
    }

    /**
     * @param MessageRole $role
     * @param Message[] $messages
     * @return Message
     */
    private function mergeMessages(MessageRole $role, array $messages): Message
    {
        if (count($messages) === 1) {
            return $messages[0];
        }

        $contentBlocks = [];
        $attachments = collect();

        $separator = "[[MESSAGE BOUNDARY]]";

        foreach ($messages as $message) {
            $contentBlocks[] = $message->content;
            if ($role === MessageRole::User && $message instanceof UserMessage) {
                $attachments = $attachments->merge($message->attachments->all());
            }
        }

        $content = implode("\n\n$separator\n\n", array_filter($contentBlocks, fn($block) => !empty(trim($block))));

        if (empty($content)) {
            $content = '&nbsp;';
        } else {
            $content = MessageMetaBlocks::createBlock(
                    'Message Boundary',
                    'Multiple messages have been merged into one. The messages are separated by the following boundary: ' . $separator
                ) . "\n\n" . $content;
        }

        if ($role === MessageRole::User) {
            return new UserMessage(
                content: $content,
                attachments: $attachments
            );
        }

        return new Message(
            role: $role,
            content: $content
        );
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): \Traversable
    {
        return $this->build();
    }

    /**
     * @inheritDoc
     */
    public function toArray(): array
    {
        return [...$this->build()];
    }
}
