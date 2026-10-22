<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Citations;

use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Citations\RagSearchToolResult;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RagSearchToolResult::class)]
class RagSearchToolResultTest extends TestCase
{
    public function testItConstructs(): void
    {
        $result = RagSearchToolResult::fromJson('{"structuredContent":{"response":{"results":[]}}}');

        static::assertNotNull($result);
        static::assertFalse($result->isRewritten());
        static::assertSame([], $result->documentReferences());
    }

    public function testItReturnsNullForUndecodableJson(): void
    {
        static::assertNull(RagSearchToolResult::fromJson('not json'));
        static::assertNull(RagSearchToolResult::fromJson('"a plain string"'));
        static::assertNull(RagSearchToolResult::fromJson('42'));
    }

    public function testItInjectsRunStableCiteIdsIntoTheStructuredContentDocumentsList(): void
    {
        $collector = new RagCitationCollector();
        $result = RagSearchToolResult::fromJson(json_encode([
            'structuredContent' => [
                'documents' => [
                    ['kind' => 'attachments', 'document_id' => 'uuid-1', 'name' => 'A.pdf'],
                    ['kind' => 'attachments', 'document_id' => 'uuid-2', 'name' => 'B.pdf'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $rewritten = $result?->withCiteIds($collector->citeIdFor(...));

        static::assertNotNull($rewritten);
        static::assertTrue($rewritten->isRewritten());

        $decoded = json_decode($rewritten->toJson(), true, JSON_THROW_ON_ERROR);

        static::assertSame('D1', $decoded['structuredContent']['documents'][0]['citeId']);
        static::assertSame('D2', $decoded['structuredContent']['documents'][1]['citeId']);

        // Second search of the run: same documents keep their cite ids, a
        // new one continues the numbering.
        $second = RagSearchToolResult::fromJson(json_encode([
            'structuredContent' => [
                'documents' => [
                    ['kind' => 'attachments', 'document_id' => 'uuid-2', 'name' => 'B.pdf'],
                    ['kind' => 'attachments', 'document_id' => 'uuid-3', 'name' => 'C.pdf'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $rewrittenSecond = $second?->withCiteIds($collector->citeIdFor(...));
        static::assertNotNull($rewrittenSecond);

        $decodedSecond = json_decode($rewrittenSecond->toJson(), true, JSON_THROW_ON_ERROR);

        static::assertSame('D2', $decodedSecond['structuredContent']['documents'][0]['citeId']);
        static::assertSame('D3', $decodedSecond['structuredContent']['documents'][1]['citeId']);
    }

    public function testItInjectsCiteIdsIntoTextContentPayloads(): void
    {
        $collector = new RagCitationCollector();
        $result = RagSearchToolResult::fromJson(json_encode([
            'content' => [
                ['type' => 'text', 'text' => json_encode([
                    'documents' => [['kind' => 'documents', 'document_id' => 'adoc_1', 'name' => 'Lecture.pdf']],
                ], JSON_THROW_ON_ERROR)],
            ],
        ], JSON_THROW_ON_ERROR));

        $rewritten = $result?->withCiteIds($collector->citeIdFor(...));

        static::assertNotNull($rewritten);
        static::assertTrue($rewritten->isRewritten());

        $decoded = json_decode($rewritten->toJson(), true, JSON_THROW_ON_ERROR);
        $text = json_decode($decoded['content'][0]['text'], true, JSON_THROW_ON_ERROR);

        static::assertSame('D1', $text['documents'][0]['citeId']);
    }

    public function testItStaysUnrewrittenWithoutDocuments(): void
    {
        $collector = new RagCitationCollector();
        $result = RagSearchToolResult::fromJson('{"structuredContent":{"response":{"results":[]}}}');

        $rewritten = $result?->withCiteIds($collector->citeIdFor(...));

        static::assertNotNull($rewritten);
        static::assertFalse($rewritten->isRewritten());
        static::assertSame('{"structuredContent":{"response":{"results":[]}}}', $rewritten->toJson());
    }

    public function testItSkipsMalformedDocumentEntries(): void
    {
        $collector = new RagCitationCollector();
        $result = RagSearchToolResult::fromJson(json_encode([
            'structuredContent' => [
                'documents' => [
                    'not-a-list',
                    ['kind' => 'attachments'],
                    ['kind' => 'attachments', 'document_id' => 'uuid-1', 'name' => 'A.pdf'],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $rewritten = $result?->withCiteIds($collector->citeIdFor(...));

        static::assertNotNull($rewritten);
        static::assertTrue($rewritten->isRewritten());

        $decoded = json_decode($rewritten->toJson(), true, JSON_THROW_ON_ERROR);

        static::assertNull($decoded['structuredContent']['documents'][0]['citeId'] ?? null);
        static::assertNull($decoded['structuredContent']['documents'][1]['citeId'] ?? null);
        static::assertSame('D1', $decoded['structuredContent']['documents'][2]['citeId']);
    }

    public function testItDerivesReferencesFromHitMetadataWithDedupe(): void
    {
        $result = RagSearchToolResult::fromJson(json_encode([
            'structuredContent' => [
                'response' => [
                    'results' => [
                        ['metadata' => ['title' => 'A.pdf', 'external_document_id' => 'uuid-1'], 'content' => 'one'],
                        ['metadata' => ['title' => 'A.pdf', 'external_document_id' => 'uuid-1'], 'content' => 'two'],
                        ['metadata' => ['title' => 'Managed', 'document_id' => 'adoc_9'], 'content' => 'three'],
                        ['metadata' => ['title' => ' ', 'external_document_id' => '  '], 'content' => 'junk'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $references = $result?->documentReferences() ?? [];

        static::assertCount(2, $references);
        static::assertSame('attachments', $references[0]->kind);
        static::assertSame('uuid-1', $references[0]->id);
        static::assertSame('A.pdf', $references[0]->name);
        static::assertSame('attachments:uuid-1', $references[0]->key());
        static::assertSame('documents', $references[1]->kind);
        static::assertSame('adoc_9', $references[1]->id);
        static::assertSame('documents:adoc_9', $references[1]->key());
    }

    public function testItDerivesReferencesFromTextContentPayloads(): void
    {
        $result = RagSearchToolResult::fromJson(json_encode([
            'content' => [
                ['type' => 'text', 'text' => json_encode([
                    'response' => [
                        'results' => [
                            ['metadata' => ['title' => 'Notes.md', 'external_document_id' => 'uuid-7'], 'content' => 'hit'],
                        ],
                    ],
                ], JSON_THROW_ON_ERROR)],
            ],
        ], JSON_THROW_ON_ERROR));

        $references = $result?->documentReferences() ?? [];

        static::assertCount(1, $references);
        static::assertSame('Notes.md', $references[0]->name);
        static::assertSame('attachments:uuid-7', $references[0]->key());
    }

    public function testItFallsBackToTheIdAsReferenceName(): void
    {
        $result = RagSearchToolResult::fromJson(json_encode([
            'structuredContent' => [
                'response' => [
                    'results' => [
                        ['metadata' => ['document_id' => 'adoc_2'], 'content' => 'hit'],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR));

        $references = $result?->documentReferences() ?? [];

        static::assertCount(1, $references);
        static::assertSame('adoc_2', $references[0]->name);
    }
}
