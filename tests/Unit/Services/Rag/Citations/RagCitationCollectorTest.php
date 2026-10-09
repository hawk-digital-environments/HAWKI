<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Citations;

use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagDocumentCitation;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RagCitationCollector::class)]
class RagCitationCollectorTest extends TestCase
{
    public function testAssignsCiteIdsInFirstSeenOrder(): void
    {
        $collector = new RagCitationCollector();

        static::assertSame('D1', $collector->citeIdFor('attachments:uuid-1'));
        static::assertSame('D2', $collector->citeIdFor('documents:adoc-7'));
        static::assertSame('D1', $collector->citeIdFor('attachments:uuid-1'));
    }

    public function testCiteIdsAndCitationsResetOnDrain(): void
    {
        $collector = new RagCitationCollector();

        $collector->citeIdFor('attachments:uuid-1');
        $collector->collect('attachments:uuid-1', new RagDocumentCitation(url: '', title: 'A.pdf'));

        $citations = $collector->drain();
        static::assertCount(1, $citations);
        static::assertSame([], $collector->drain());

        // Numbering restarts — a new run assigns from D1 again.
        static::assertSame('D1', $collector->citeIdFor('attachments:uuid-2'));
    }

    public function testCitationsCarryTheirCiteId(): void
    {
        $collector = new RagCitationCollector();

        $citeId = $collector->citeIdFor('attachments:uuid-1');
        $collector->collect('attachments:uuid-1', new RagDocumentCitation(url: 'https://proxy/doc', title: 'A.pdf', citeId: $citeId));

        $citations = $collector->drain();

        static::assertSame('D1', $citations[0]->citeId);
        static::assertSame('D1', $citations[0]->toArray()['citeId']);
        static::assertTrue($citations[0]->toArray()['document']);
    }
}
