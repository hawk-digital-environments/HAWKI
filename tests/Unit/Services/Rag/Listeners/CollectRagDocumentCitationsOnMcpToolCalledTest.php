<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Rag\Listeners;

use App\Models\Ai\AiTool;
use App\Models\Ai\McpServer;
use App\Models\Assistants\AssistantAttachment;
use App\Services\Ai\Tools\LaravelAi\Events\McpToolCalledFilterEvent;
use App\Services\Ai\Tools\Mcp\HawkiMcpClient;
use App\Services\Ai\Tools\Values\ToolType;
use App\Services\Rag\Citations\RagCitationCollector;
use App\Services\Rag\Listeners\CollectRagDocumentCitationsOnMcpToolCalled;
use App\Services\Assistant\Repositories\AssistantAttachmentRepository;
use App\Services\Ai\Values\OnlineStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(CollectRagDocumentCitationsOnMcpToolCalled::class)]
class CollectRagDocumentCitationsOnMcpToolCalledTest extends TestCase
{
    public function testMapsManagedDocumentReferencesOntoAttachmentUrls(): void
    {
        $attachment = new AssistantAttachment(['uuid' => 'doc-uuid-1', 'name' => 'Lecture.pdf']);
        $attachment->exists = true;

        $listener = $this->listener(byDocumentId: $attachment);

        $listener->handle($this->event($this->tool('hawki-rag'), $this->resultWithHits([
            ['metadata' => ['title' => 'Lecture.pdf', 'document_id' => 'adoc_1'], 'content' => 'chunk'],
        ])));

        $citations = $this->collector()->drain();
        static::assertCount(1, $citations);
        static::assertSame('Lecture.pdf', $citations[0]->title);
        static::assertTrue($citations[0]->document);
        static::assertStringContainsString('/api/hawki/v1/proxy/storage/', $citations[0]->url);
        static::assertStringContainsString('doc-uuid-1', $citations[0]->url);
    }

    public function testMapsExternalDocumentReferencesOntoAttachmentUrls(): void
    {
        $attachment = new AssistantAttachment(['uuid' => 'doc-uuid-1', 'name' => 'Lecture.pdf']);
        $attachment->exists = true;

        $listener = $this->listener(byUuid: $attachment);

        $listener->handle($this->event($this->tool('hawki-rag'), $this->resultWithHits([
            ['metadata' => ['title' => 'Lecture.pdf', 'external_document_id' => 'doc-uuid-1'], 'content' => 'chunk'],
        ])));

        $citations = $this->collector()->drain();
        static::assertCount(1, $citations);
        static::assertSame('Lecture.pdf', $citations[0]->title);
        static::assertStringContainsString('/api/hawki/v1/proxy/storage/', $citations[0]->url);
        static::assertStringContainsString('doc-uuid-1', $citations[0]->url);
    }

    public function testCitesUnresolvedReferencesByNameOnly(): void
    {
        $listener = $this->listener();

        $listener->handle($this->event($this->tool('hawki-rag'), $this->resultWithHits([
            ['metadata' => ['title' => 'Article.pdf', 'external_document_id' => 'missing-uuid'], 'content' => 'chunk'],
        ])));

        $citations = $this->collector()->drain();
        static::assertCount(1, $citations);
        static::assertSame('Article.pdf', $citations[0]->title);
        static::assertSame('', $citations[0]->url);
    }

    public function testIgnoresServersAbsentFromTheConfig(): void
    {
        $listener = $this->listener();

        $listener->handle($this->event($this->tool('some-other-server'), $this->resultWithHits([
            ['metadata' => ['title' => 'Article.pdf', 'external_document_id' => 'doc-uuid-1'], 'content' => 'chunk'],
        ])));

        static::assertSame([], $this->collector()->drain());
    }

    public function testHonoursADisabledMappingFlag(): void
    {
        config(['tools.mcp_servers.hawki-rag.map_document_to_attachment' => false]);

        $listener = $this->listener();

        $listener->handle($this->event($this->tool('hawki-rag'), $this->resultWithHits([
            ['metadata' => ['title' => 'Article.pdf', 'external_document_id' => 'doc-uuid-1'], 'content' => 'chunk'],
        ])));

        static::assertSame([], $this->collector()->drain());
    }

    public function testFallsBackToTheTextContentWhenStructuredContentIsMissing(): void
    {
        $listener = $this->listener();

        $textPayload = json_encode([
            'response' => [
                'results' => [
                    ['metadata' => ['title' => 'Notes.md', 'document_id' => 'adoc_9'], 'content' => 'chunk'],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $result = json_encode([
            'content' => [
                ['type' => 'text', 'text' => $textPayload],
            ],
            'isError' => false,
        ], JSON_THROW_ON_ERROR);

        $listener->handle($this->event($this->tool('hawki-rag'), $result));

        $citations = $this->collector()->drain();
        static::assertCount(1, $citations);
        static::assertSame('Notes.md', $citations[0]->title);
    }

    public function testCollectsEachDocumentOnlyOnce(): void
    {
        $listener = $this->listener();

        $listener->handle($this->event($this->tool('hawki-rag'), $this->resultWithHits([
            ['metadata' => ['title' => 'A.pdf', 'external_document_id' => 'uuid-1'], 'content' => 'chunk one'],
            ['metadata' => ['title' => 'A.pdf', 'external_document_id' => 'uuid-1'], 'content' => 'chunk two'],
        ])));

        static::assertCount(1, $this->collector()->drain());
    }

    protected function setUp(): void
    {
        parent::setUp();

        config(['tools.mcp_servers' => [
            'hawki-rag' => [
                'url' => 'http://hawki_rag_app/hawki_rag',
                'server_label' => 'hawki-rag',
            ],
        ]]);
    }

    private function listener(?AssistantAttachment $byDocumentId = null, ?AssistantAttachment $byUuid = null): CollectRagDocumentCitationsOnMcpToolCalled
    {
        $repository = $this->createMock(AssistantAttachmentRepository::class);
        $repository->method('findOneByRagDocumentId')->willReturn($byDocumentId);
        $repository->method('findOneByUuid')->willReturn($byUuid);

        return new CollectRagDocumentCitationsOnMcpToolCalled(
            attachments: $repository,
            citations: $this->collector(),
            logger: new \Psr\Log\NullLogger(),
        );
    }

    private function collector(): RagCitationCollector
    {
        // Same scoped instance the container hands to the agent adapter.
        return $this->app->make(RagCitationCollector::class);
    }

    private function tool(string $serverLabel): AiTool
    {
        $aiTool = new AiTool([
            'name' => 'hawki-rag-query-search',
            'mcp_name' => 'query-search',
            'description' => 'Search the knowledge base.',
            'type' => ToolType::MCP,
        ]);

        $server = new McpServer([
            'url' => 'http://hawki_rag_app/hawki_rag',
            'server_label' => $serverLabel,
            'status' => OnlineStatus::ONLINE->value,
        ]);

        return $aiTool->setRelation('server', $server);
    }

    private function event(AiTool $tool, string $result): McpToolCalledFilterEvent
    {
        return new McpToolCalledFilterEvent(
            result: $result,
            arguments: [],
            tool: $tool,
            mcpClient: $this->createMock(HawkiMcpClient::class),
        );
    }

    /**
     * @param list<array<string, mixed>> $results
     */
    private function resultWithHits(array $results): string
    {
        return json_encode([
            'structuredContent' => [
                'response' => ['results' => $results],
            ],
            'content' => [
                ['type' => 'text', 'text' => '{"response": {"results": []}}'],
            ],
            'isError' => false,
        ], JSON_THROW_ON_ERROR);
    }
}
