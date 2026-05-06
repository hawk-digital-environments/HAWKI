<?php

namespace Tests\Feature\Api\Assistant\Fixtures;

use App\Models\Ai\Tools\AiTool;
use Illuminate\Support\Facades\DB;

trait Assistant
{
    private function createPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Assistant',
            'system_prompt' => 'You are a helpful assistant.',
            'greeting' => 'Hello!',
            'description' => 'A test assistant.',
            'detail_description' => 'Detailed description here.',
            'allow_remix' => true,
            'allow_model_select' => false,
            'language' => 'en',
            'category' => 'general',
            'release_stage' => 'private',
            'formality' => 'neutral',
            'model' => 'gpt-4',
            'model_length' => 2048,
            'model_temp' => 0.7,
            'model_top_p' => 0.9,
        ], $overrides);
    }

    private function createAiTool(): AiTool
    {
        $serverId = DB::table('mcp_servers')->insertGetId([
            'url' => 'https://example.com/mcp/' . uniqid(),
            'server_label' => 'Test Server ' . uniqid(),
            'timeout' => '10',
            'discovery_timeout' => '10',
            'api_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_' . uniqid(),
            'description' => 'A test tool',
            'status' => 'active',
            'server_id' => $serverId,
        ]);
    }
}
