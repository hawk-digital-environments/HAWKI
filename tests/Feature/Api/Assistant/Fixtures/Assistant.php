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
            'release_stage' => 'private',
            'formality' => 'neutral',
            'model' => 'gpt-4',
            'model_length' => 2048,
            'model_temp' => 0.7,
            'model_top_p' => 0.9,
        ], $overrides);
    }

    private function createRelationships(array $rels = []): array
    {
        $defaults = [];
        if (isset($rels['language'])) {
            $defaults['language'] = ['data' => ['type' => 'languages', 'id' => (string) $rels['language']]];
        }
        if (isset($rels['category'])) {
            $defaults['category'] = ['data' => ['type' => 'categories', 'id' => (string) $rels['category']]];
        }
        if (isset($rels['tags'])) {
            $defaults['tags'] = ['data' => array_map(fn ($id) => ['type' => 'tags', 'id' => (string) $id], $rels['tags'])];
        }
        if (isset($rels['ai_tools'])) {
            $defaults['ai_tools'] = ['data' => array_map(fn ($id) => ['type' => 'ai-tools', 'id' => (string) $id], $rels['ai_tools'])];
        }
        if (isset($rels['user_prompts'])) {
            $defaults['user_prompts'] = ['data' => array_map(fn ($id) => ['type' => 'user-prompts', 'id' => (string) $id], $rels['user_prompts'])];
        }
        return $defaults;
    }

    private function createJsonApiPayload(array $attrOverrides = [], array $relOverrides = []): array
    {
        $doc = [
            'data' => [
                'type' => 'assistants',
                'attributes' => $this->createPayload($attrOverrides),
            ],
        ];
        $rels = $this->createRelationships($relOverrides);
        if ($rels) {
            $doc['data']['relationships'] = $rels;
        }
        return $doc;
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
