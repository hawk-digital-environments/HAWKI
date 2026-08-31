<?php

declare(strict_types=1);

namespace Tests\Feature\Api\Assistant\Fixtures;

use App\Models\Ai\AiTool;
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
            'model' => 'gpt-4',
            'max_tokens' => 2048,
            'temp' => 0.7,
            'top_p' => 0.9,
        ], $overrides);
    }

    private function createRelationships(array $rels = []): array
    {
        $defaults = [];

        if (isset($rels['assistant_category'])) {
            $defaults['assistant_category'] = ['data' => ['type' => 'assistant-categories', 'id' => (string) $rels['assistant_category']]];
        }

        if (isset($rels['assistant_tags'])) {
            $defaults['assistant_tags'] = ['data' => array_map(static fn ($id) => ['type' => 'assistant-tags', 'id' => (string) $id], $rels['assistant_tags'])];
        }

        if (isset($rels['ai_tools'])) {
            $defaults['ai_tools'] = ['data' => array_map(static fn ($id) => ['type' => 'ai-tools', 'id' => (string) $id], $rels['ai_tools'])];
        }

        if (isset($rels['assistant_user_prompts'])) {
            $defaults['assistant_user_prompts'] = ['data' => array_map(static fn ($id) => ['type' => 'assistant-user-prompts', 'id' => (string) $id], $rels['assistant_user_prompts'])];
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
            'api_key' => 'test-key',
            'timeouts' => '[]',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return AiTool::create([
            'type' => 'function',
            'name' => 'test_tool_' . uniqid(),
            'description' => 'A test tool',
            'active' => true,
            'mcp_server_id' => $serverId,
        ]);
    }
}
