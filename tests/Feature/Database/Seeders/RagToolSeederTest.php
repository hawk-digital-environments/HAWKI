<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\AiToolSeeder;
use Database\Seeders\Rag\RagToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(RagToolSeeder::class)]
class RagToolSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on whether the host .env points
        // HAWKI_RAG_MCP_API_URL at a live system: pin the branch the
        // test exercises via the config the seeder actually reads.
        config(['tools.mcp_servers.hawki-rag' => [
            'url' => 'http://localhost:8080/mcp/rawki',
            'server_label' => 'hawki-rag',
            'api_key' => null,
        ]]);
    }

    public function testItSeedsTheRealRagConfigurationWhenConfigured(): void
    {
        config(['tools.mcp_servers.hawki-rag' => [
            'url' => 'http://rag.real.test/mcp',
            'server_label' => 'hawki-rag',
            'description' => 'Real HAWKI RAG',
            'require_approval' => 'never',
            'connection_timeout' => 11,
            'read_timeout' => 22,
            'api_key' => 'real-rag-key',
        ]]);

        // A live hawki-rag row already registered (the second
        // `realRagConfigured()` condition) — with a known status the
        // re-seeded row must preserve.
        $existingId = DB::table('mcp_servers')->insertGetId([
            'url' => 'http://rag.real.test/mcp',
            'server_label' => 'hawki-rag',
            'description' => 'stale',
            'require_approval' => 'never',
            'api_key' => null,
            'timeouts' => '{}',
            'type' => 'sse',
            'status' => 'online',
            'added_by_file' => false,
        ]);

        $this->seedModels(5);

        $this->seed(RagToolSeeder::class);

        $server = DB::table('mcp_servers')->find($existingId);

        static::assertSame('http://rag.real.test/mcp', $server->url);
        static::assertSame('Real HAWKI RAG', $server->description);
        static::assertSame('hawki-rag', $server->server_label);
        static::assertSame('sse', $server->type);
        static::assertSame(1, $server->added_by_file, 'the real row is config-owned');
        static::assertSame('online', $server->status, 'a live row\'s known status survives the re-seed');
        static::assertSame(
            ['read' => 22, 'connect' => 11],
            json_decode($server->timeouts, true),
        );

        // The built-in tools exist, point at the real server, and carry the
        // live server's tool names — a stale mcp_name makes every call fail
        // with "Tool not found" on the MCP server.
        $ragTools = ['hawki-rag-web-search-tool' => 'web-search-tool', 'hawki-rag-query-search' => 'query-search'];

        foreach ($ragTools as $toolName => $mcpName) {
            $this->assertDatabaseHas('ai_tools', [
                'name' => $toolName,
                'mcp_name' => $mcpName,
                'mcp_server_id' => $existingId,
            ]);
        }

        $querySearchConfig = (string) DB::table('ai_tools')->where('name', 'hawki-rag-query-search')->value('mcp_config');

        static::assertStringContainsString(
            'top_k',
            $querySearchConfig,
            'the seeded schema mirrors the server-advertised parameters',
        );
        static::assertStringNotContainsString(
            'dataset_id',
            $querySearchConfig,
            'dataset_id is injected server-side and must stay hidden from the model',
        );

        // The rag tools reach every tool-calling model.
        $ragToolIds = DB::table('ai_tools')
            ->whereIn('name', array_keys($ragTools))
            ->pluck('id');

        $allModels = DB::table('ai_models')->where('settings', 'like', '%tool_calling%')->pluck('id');

        static::assertCount(5, $allModels);

        foreach ($allModels as $modelId) {
            $assignedRagTools = DB::table('ai_model_tools')
                ->where('ai_model_id', $modelId)
                ->whereIn('ai_tool_id', $ragToolIds)
                ->count();

            static::assertSame(2, $assignedRagTools, "model {$modelId} must carry both hawki-rag tools");
        }
    }

    public function testItSeedsTheRagMockWhenNothingRealIsConfigured(): void
    {
        $this->seedModels(2);

        $this->seed(RagToolSeeder::class);

        $this->assertDatabaseHas('mcp_servers', [
            'url' => 'https://rag.mock.hawki.test/mcp',
            'server_label' => 'hawki-rag',
            'added_by_file' => 0,
        ]);

        $this->assertDatabaseMissing('mcp_servers', [
            'url' => 'http://localhost:8080/mcp/rawki',
        ]);

        $this->assertDatabaseHas('ai_tools', ['name' => 'hawki-rag-web-search-tool']);
        $this->assertDatabaseHas('ai_tools', ['name' => 'hawki-rag-query-search']);

        // Mock branch or not: the rag tools land on every tool-calling model.
        $ragToolIds = DB::table('ai_tools')
            ->whereIn('name', ['hawki-rag-web-search-tool', 'hawki-rag-query-search'])
            ->pluck('id');

        foreach (DB::table('ai_models')->where('settings', 'like', '%tool_calling%')->pluck('id') as $modelId) {
            static::assertSame(
                2,
                DB::table('ai_model_tools')->where('ai_model_id', $modelId)->whereIn('ai_tool_id', $ragToolIds)->count(),
            );
        }
    }

    public function testItSeedsToolNamesFromTheRagConfig(): void
    {
        config(['rag.query_tool' => 'custom-kb-search', 'rag.web_search_tool' => 'custom-web-search']);

        $this->seed(RagToolSeeder::class);

        $this->assertDatabaseHas('ai_tools', ['name' => 'custom-kb-search', 'mcp_name' => 'query-search']);
        $this->assertDatabaseHas('ai_tools', ['name' => 'custom-web-search', 'mcp_name' => 'web-search-tool']);
    }

    public function testItDoesNotAssignToolsToNonToolCallingModels(): void
    {
        $this->seedModels(2, withNonToolCallingModel: true);

        $this->seed(RagToolSeeder::class);

        $nonToolModelId = DB::table('ai_models')->where('settings', 'not like', '%tool_calling%')->value('id');

        static::assertSame(
            0,
            DB::table('ai_model_tools')->where('ai_model_id', $nonToolModelId)->count(),
            'models without tool calling must not receive any tools',
        );
    }

    /**
     * Inserts a provider plus the given number of tool-calling models and
     * (optionally) one model without tool calling. Raw inserts because no
     * AiModel factory exists; the JSON columns hydrate through their casts.
     */
    private function seedModels(int $toolCallingCount, bool $withNonToolCallingModel = false): void
    {
        $providerId = DB::table('ai_providers')->insertGetId([
            'provider_id' => 'seeder-test',
            'name' => 'Seeder Test Provider',
            'adapter_key' => 'openAi',
            'active' => true,
        ]);

        $base = [
            'provider_id' => $providerId,
            'active' => true,
            'native_capabilities' => '[]',
            'limits' => '{}',
            'pricing' => '{}',
            'flags' => '[]',
        ];

        $modelIds = [];
        foreach (range(1, $toolCallingCount) as $i) {
            $modelIds[] = DB::table('ai_models')->insertGetId($base + [
                'model_id' => "seeder-test-model-{$i}",
                'label' => "Seeder Test Model {$i}",
                'settings' => '{"tool_calling": true}',
            ]);
        }

        // AiModel's usage-type global scope only surfaces models that carry a
        // usage rule for the current context — seed one per model so the
        // seeder's assign pass actually finds them.
        foreach ($modelIds as $modelId) {
            DB::table('ai_model_usage_rules')->insert(['ai_model_id' => $modelId, 'usage_type' => 'main']);
        }

        if ($withNonToolCallingModel) {
            DB::table('ai_models')->insert($base + [
                'model_id' => 'seeder-test-model-plain',
                'label' => 'Seeder Test Model (no tools)',
                'settings' => '{"tool_calling": false}',
            ]);
        }
    }
}
