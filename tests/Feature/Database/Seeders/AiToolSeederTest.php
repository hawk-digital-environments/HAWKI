<?php

declare(strict_types=1);

namespace Tests\Feature\Database\Seeders;

use Database\Seeders\AiToolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AiToolSeeder::class)]
class AiToolSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Tests must not depend on whether the host .env points
        // HAWKI_RAG_MCP_API_URL at a live system: pin the config the
        // seeder's RAG branch would read (the RAG rows themselves are
        // seeded by RagToolSeeder, see RagToolSeederTest).
        config(['tools.mcp_servers.hawki-rag' => [
            'url' => 'http://localhost:8080/mcp/rawki',
            'server_label' => 'hawki-rag',
            'api_key' => null,
        ]]);
    }

    public function testItSeedsTheMockServersAndTools(): void
    {
        $this->seedModels(5);

        $this->seed(AiToolSeeder::class);

        $this->assertDatabaseHas('mcp_servers', ['server_label' => 'github-tools']);
        $this->assertDatabaseHas('mcp_servers', ['server_label' => 'local-files']);
        $this->assertDatabaseHas('mcp_servers', ['server_label' => 'weather-service']);

        // The RAG module ships its own rows; the core tool seeder stays
        // RAG-free.
        $this->assertDatabaseMissing('mcp_servers', ['server_label' => 'hawki-rag']);
        $this->assertDatabaseMissing('ai_tools', ['name' => 'hawki-rag-query-search']);
        $this->assertDatabaseMissing('ai_tools', ['name' => 'hawki-rag-web-search-tool']);

        $this->assertDatabaseHas('ai_tools', ['name' => 'github-tools-search_repositories']);
        $this->assertDatabaseHas('ai_tools', ['name' => 'test_tool']);

        // The demo mocks keep their "first three models" spread.
        $mockToolId = DB::table('ai_tools')->where('name', 'github-tools-search_repositories')->value('id');

        static::assertSame(
            3,
            DB::table('ai_model_tools')->where('ai_tool_id', $mockToolId)->count(),
            'the github mock keeps its take(3) spread',
        );
    }

    public function testItDoesNotAssignToolsToNonToolCallingModels(): void
    {
        $this->seedModels(2, withNonToolCallingModel: true);

        $this->seed(AiToolSeeder::class);

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
