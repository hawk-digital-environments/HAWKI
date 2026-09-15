<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Agents\Responses;

use App\Services\Ai\Agents\Responses\RagCitationAwareStreamableResponse;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagDocumentCitation;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Citation;
use Laravel\Ai\Streaming\Events\StreamEnd;
use Laravel\Ai\Streaming\Events\TextDelta;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RagCitationAwareStreamableResponse::class)]
class RagCitationAwareStreamableResponseTest extends TestCase
{
    public function testYieldsDocumentCitationsBeforeStreamEnd(): void
    {
        $collector = new RagCitationCollector;
        $collector->collect('attachments:uuid-1', new RagDocumentCitation(url: '/storage/doc', title: 'Lecture.pdf'));

        $inner = new StreamableAgentResponse(
            invocationId: 'inv_1',
            generator: static function (): \Generator {
                yield new TextDelta('t1', 'm1', 'Hello ', 1);
                yield new TextDelta('t2', 'm1', 'world', 2);
                yield new StreamEnd('e1', 'stop', new Usage, 3);
            },
            meta: new Meta,
        );

        $response = new RagCitationAwareStreamableResponse($inner, fn (): array => $collector->drain());

        $events = iterator_to_array($response, false);

        static::assertCount(4, $events);
        static::assertInstanceOf(TextDelta::class, $events[0]);
        static::assertInstanceOf(TextDelta::class, $events[1]);
        static::assertInstanceOf(Citation::class, $events[2], 'the document citation arrives before StreamEnd');
        static::assertInstanceOf(RagDocumentCitation::class, $events[2]->citation);
        static::assertSame('Lecture.pdf', $events[2]->citation->title);
        static::assertInstanceOf(StreamEnd::class, $events[3], 'StreamEnd stays last');

        static::assertSame('Hello world', $response->text, 'text accumulation survives the wrapping');
    }

    public function testDrainIsOneShot(): void
    {
        $collector = new RagCitationCollector;
        $collector->collect('attachments:uuid-1', new RagDocumentCitation(url: '', title: 'A.pdf'));

        $inner = new StreamableAgentResponse(
            invocationId: 'inv_2',
            generator: static function (): \Generator {
                yield new StreamEnd('e1', 'stop', new Usage, 1);
            },
            meta: new Meta,
        );

        $response = new RagCitationAwareStreamableResponse($inner, fn (): array => $collector->drain());

        // The inherited getIterator replays captured events on later reads,
        // but the collector itself must be empty after the first drain.
        iterator_to_array($response, false);

        static::assertSame([], $collector->drain());
    }

    public function testInnerThenCallbacksStillFire(): void
    {
        $inner = new StreamableAgentResponse(
            invocationId: 'inv_3',
            generator: static function (): \Generator {
                yield new StreamEnd('e1', 'stop', new Usage, 1);
            },
            meta: new Meta,
        );

        $fired = false;
        $inner->then(static function () use (&$fired): void {
            $fired = true;
        });

        $response = new RagCitationAwareStreamableResponse($inner, fn (): array => []);
        iterator_to_array($response, false);

        static::assertTrue($fired, 'usage tracking registered on the inner response still runs');
    }
}
