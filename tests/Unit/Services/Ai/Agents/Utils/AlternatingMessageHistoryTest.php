<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Utils;

use App\Services\Ai\Agents\Utils\AlternatingMessageHistory;
use App\Services\Ai\Agents\Utils\MessageMetaBlocks;
use Laravel\Ai\Messages\Message;
use Laravel\Ai\Messages\MessageRole;
use Laravel\Ai\Messages\UserMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AlternatingMessageHistory::class)]
class AlternatingMessageHistoryTest extends TestCase
{
    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertInstanceOf(AlternatingMessageHistory::class, $sut);
    }

    // =========================================================================
    // Empty history
    // =========================================================================

    public function testItReturnsEmptyWhenNoMessagesRegistered(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertSame([], $sut->toArray());
    }

    // =========================================================================
    // registerAiMessage
    // =========================================================================

    public function testItRegistersAiMessageAsAssistantRole(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerAiMessage('Hello from AI');
        $messages = $sut->toArray();

        static::assertCount(1, $messages);
        static::assertSame(MessageRole::Assistant, $messages[0]->role);
        static::assertSame('Hello from AI', $messages[0]->content);
    }

    public function testItRegistersAiMessageReturnsSelf(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertSame($sut, $sut->registerAiMessage('hello'));
    }

    // =========================================================================
    // registerUserMessage
    // =========================================================================

    public function testItRegistersUserMessageAsUserRole(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('Hello from user');
        $messages = $sut->toArray();

        static::assertCount(1, $messages);
        static::assertInstanceOf(UserMessage::class, $messages[0]);
        static::assertSame('Hello from user', $messages[0]->content);
    }

    public function testItRegistersUserMessageReturnsSelf(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertSame($sut, $sut->registerUserMessage('hello'));
    }

    // =========================================================================
    // Already-alternating sequence — no merging needed
    // =========================================================================

    public function testItPassesThroughAlreadyAlternatingSequence(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('user 1');
        $sut->registerAiMessage('ai 1');
        $sut->registerUserMessage('user 2');
        $sut->registerAiMessage('ai 2');

        $messages = $sut->toArray();

        static::assertCount(4, $messages);
        static::assertSame(MessageRole::User, $messages[0]->role);
        static::assertSame(MessageRole::Assistant, $messages[1]->role);
        static::assertSame(MessageRole::User, $messages[2]->role);
        static::assertSame(MessageRole::Assistant, $messages[3]->role);
    }

    // =========================================================================
    // Consecutive same-role messages — merging
    // =========================================================================

    public function testItMergesTwoConsecutiveUserMessages(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('first user');
        $sut->registerUserMessage('second user');

        $messages = $sut->toArray();

        static::assertCount(1, $messages);
        static::assertSame(MessageRole::User, $messages[0]->role);
        static::assertStringContainsString('first user', $messages[0]->content);
        static::assertStringContainsString('second user', $messages[0]->content);
    }

    public function testItMergesTwoConsecutiveAiMessages(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerAiMessage('first ai');
        $sut->registerAiMessage('second ai');

        $messages = $sut->toArray();

        static::assertCount(1, $messages);
        static::assertSame(MessageRole::Assistant, $messages[0]->role);
        static::assertStringContainsString('first ai', $messages[0]->content);
        static::assertStringContainsString('second ai', $messages[0]->content);
    }

    public function testItMergesThreeConsecutiveUserMessages(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('a');
        $sut->registerUserMessage('b');
        $sut->registerUserMessage('c');

        $messages = $sut->toArray();

        static::assertCount(1, $messages);
        static::assertStringContainsString('a', $messages[0]->content);
        static::assertStringContainsString('b', $messages[0]->content);
        static::assertStringContainsString('c', $messages[0]->content);
    }

    // =========================================================================
    // Merged messages — MESSAGE BOUNDARY separator
    // =========================================================================

    public function testItIncludesMessageBoundarySeparatorWhenMerging(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('alpha');
        $sut->registerUserMessage('beta');

        $messages = $sut->toArray();

        static::assertStringContainsString('[[MESSAGE BOUNDARY]]', $messages[0]->content);
    }

    public function testItIncludesMessageBoundaryMetaBlockWhenMerging(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('alpha');
        $sut->registerUserMessage('beta');

        $messages = $sut->toArray();

        static::assertStringContainsString('[HKI_META_MESSAGE_BOUNDARY]', $messages[0]->content);
    }

    // =========================================================================
    // Merged messages — empty content fallback
    // =========================================================================

    public function testItUsesNbspWhenMergedContentIsAllEmpty(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('');
        $sut->registerUserMessage('');

        $messages = $sut->toArray();

        static::assertSame('&nbsp;', $messages[0]->content);
    }

    // =========================================================================
    // Mixed sequences needing reduction
    // =========================================================================

    public function testItReducesUserUserAssistantUserToThreeMessages(): void
    {
        // user, user, assistant, user → user(merged), assistant, user
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('u1');
        $sut->registerUserMessage('u2');
        $sut->registerAiMessage('a1');
        $sut->registerUserMessage('u3');

        $messages = $sut->toArray();

        static::assertCount(3, $messages);
        static::assertSame(MessageRole::User, $messages[0]->role);
        static::assertStringContainsString('u1', $messages[0]->content);
        static::assertStringContainsString('u2', $messages[0]->content);
        static::assertSame(MessageRole::Assistant, $messages[1]->role);
        static::assertSame(MessageRole::User, $messages[2]->role);
        static::assertSame('u3', $messages[2]->content);
    }

    public function testItReducesUserAssistantAssistantUserToThreeMessages(): void
    {
        // user, assistant, assistant, user → user, assistant(merged), user
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('u1');
        $sut->registerAiMessage('a1');
        $sut->registerAiMessage('a2');
        $sut->registerUserMessage('u2');

        $messages = $sut->toArray();

        static::assertCount(3, $messages);
        static::assertSame(MessageRole::User, $messages[0]->role);
        static::assertSame(MessageRole::Assistant, $messages[1]->role);
        static::assertStringContainsString('a1', $messages[1]->content);
        static::assertStringContainsString('a2', $messages[1]->content);
        static::assertSame(MessageRole::User, $messages[2]->role);
    }

    // =========================================================================
    // Merged UserMessage preserves attachments
    // =========================================================================

    public function testItMergedUserMessageIsInstanceOfUserMessage(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('msg1');
        $sut->registerUserMessage('msg2');

        $messages = $sut->toArray();

        static::assertInstanceOf(UserMessage::class, $messages[0]);
    }

    public function testItMergedAssistantMessageIsNotUserMessage(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerAiMessage('ai1');
        $sut->registerAiMessage('ai2');

        $messages = $sut->toArray();

        static::assertNotInstanceOf(UserMessage::class, $messages[0]);
        static::assertInstanceOf(Message::class, $messages[0]);
    }

    // =========================================================================
    // getIterator / IteratorAggregate
    // =========================================================================

    public function testItImplementsIteratorAggregate(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertInstanceOf(\IteratorAggregate::class, $sut);
    }

    public function testItIteratesOverBuiltMessages(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('hello');
        $sut->registerAiMessage('world');

        $messages = [];
        foreach ($sut as $message) {
            $messages[] = $message;
        }

        static::assertCount(2, $messages);
    }

    // =========================================================================
    // toArray / Arrayable
    // =========================================================================

    public function testItImplementsArrayable(): void
    {
        $sut = new AlternatingMessageHistory();
        static::assertInstanceOf(\Illuminate\Contracts\Support\Arrayable::class, $sut);
    }

    public function testItToArrayReturnsSameResultAsIterating(): void
    {
        $sut = new AlternatingMessageHistory();
        $sut->registerUserMessage('u1');
        $sut->registerAiMessage('a1');

        $fromArray = $sut->toArray();
        $fromIterator = [];
        foreach ($sut as $message) {
            $fromIterator[] = $message;
        }

        static::assertEquals($fromArray, $fromIterator);
    }
}
