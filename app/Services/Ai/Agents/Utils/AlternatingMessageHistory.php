<?php
declare(strict_types=1);


namespace App\Services\Ai\Agents\Utils;


use Illuminate\Contracts\Support\Arrayable;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;

// The job of this class is to enforce the alternating pattern of messages between user and assistant,
// initially I tried to simply pass along an empty message for the assistant if the user sent two messages in a row,
// but that caused issues with the model's context, so instead we will merge all messages from the same role into a single message.
// This way a pattern of user, user, assistant, user, assistant will become user, assistant, user, assistant, and a pattern of user,
// assistant, assistant, user will become user, assistant, user.
class AlternatingMessageHistory implements \IteratorAggregate, Arrayable
{
    /**
     * @var Message[]
     */
    private array $messages = [];

    public function registerAiMessage(string $content): self
    {
        $this->messages[] = new Message(
            role: MessageRole::Assistant,
            content: $content
        );

        return $this;
    }

    public function registerUserMessage(string $content, UserMessageAttachments|null $attachments = null): self
    {
        $message = new UserMessage(
            content: $content
        );
        $attachments?->apply($message);
        $this->messages[] = $message;
        return $this;
    }

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
