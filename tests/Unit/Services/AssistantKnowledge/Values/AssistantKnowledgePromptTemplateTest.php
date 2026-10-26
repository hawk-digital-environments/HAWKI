<?php

declare(strict_types=1);

namespace Tests\Unit\Services\AssistantKnowledge\Values;

use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\AssistantKnowledge\Values\AssistantKnowledgePromptTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantKnowledgePromptTemplate::class)]
class AssistantKnowledgePromptTemplateTest extends TestCase
{
    public function testRendersTheToolName(): void
    {
        static::assertStringContainsString('Call the hawki-rag-query-search tool at most 5 times per answer', strtr(
            AssistantKnowledgePromptTemplate::KNOWLEDGE_TOOL,
            ['{{tool_name}}' => 'hawki-rag-query-search'],
        ));
    }

    public function testContainsTheCompletenessRule(): void
    {
        $template = AssistantKnowledgePromptTemplate::KNOWLEDGE_TOOL;

        static::assertStringContainsString('### Completeness Rule', $template);
        static::assertStringContainsString('Answer every part of the user\'s question.', $template);
        static::assertStringContainsString('include them exactly. Do not summarize them away.', $template);
        static::assertStringContainsString('write them as a list. Do not compress them into one sentence.', $template);
    }

    public function testQueryRuleContainsTheNearMissRule(): void
    {
        $template = AssistantKnowledgePromptTemplate::KNOWLEDGE_TOOL;

        static::assertStringContainsString('a near miss, not evidence', $template);
        static::assertStringContainsString('Prefer no evidence over near-miss evidence.', $template);
    }

    public function testPromptBudgetStaysInSyncWithTheDeclaredNonStreamingDefault(): void
    {
        $default = $this->app->make(AiModelSettingRegistry::class)
            ->get(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS);

        static::assertStringContainsString(
            sprintf('at most %d times per answer', $default),
            AssistantKnowledgePromptTemplate::KNOWLEDGE_TOOL,
        );
    }
}
