<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Assistant;

use App\Services\Assistant\Values\AssistantPromptTemplate;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(AssistantPromptTemplate::class)]
class AssistantPromptTemplateTest extends TestCase
{
    public function testAnswerLengthWithoutBudgetReturnsEmptyString(): void
    {
        self::assertSame('', AssistantPromptTemplate::answerLength(0));
        self::assertSame('', AssistantPromptTemplate::answerLength(-100));
    }

    public function testAnswerLengthWithoutStyleOmitsStyleParts(): void
    {
        $fragment = AssistantPromptTemplate::answerLength(1000);

        self::assertStringContainsString('[OUTPUT LENGTH CONTROL MODULE]', $fragment);
        self::assertStringContainsString('- max_tokens: 1000', $fragment);
        self::assertStringContainsString('### Token Budget Rules', $fragment);
        self::assertStringNotContainsString('### Style Cooperation', $fragment);
        self::assertStringNotContainsString('- style:', $fragment);
        self::assertStringNotContainsString('{{', $fragment);
    }

    public function testAnswerLengthWithStyleCombinesBothParts(): void
    {
        $fragment = AssistantPromptTemplate::answerLength(1000, 'concise');

        self::assertStringContainsString('- max_tokens: 1000', $fragment);
        self::assertStringContainsString('- style: concise', $fragment);
        self::assertStringContainsString('### Style Cooperation', $fragment);
        self::assertStringContainsString('`concise` defines the verbosity TARGET', $fragment);
        self::assertStringContainsString('max_tokens defines the hard CEILING', $fragment);
        self::assertStringNotContainsString('{{', $fragment);
    }

    public function testAnswerLengthTreatsBlankStyleAsNoStyle(): void
    {
        $expected = AssistantPromptTemplate::answerLength(1000);

        self::assertSame($expected, AssistantPromptTemplate::answerLength(1000, ''));
        self::assertSame($expected, AssistantPromptTemplate::answerLength(1000, '   '));
        self::assertSame($expected, AssistantPromptTemplate::answerLength(1000, null));
    }

    public function testAnswerStyleTemplateSubstitutesStyleValue(): void
    {
        $fragment = str_replace('{{value}}', 'detailed', AssistantPromptTemplate::ANSWER_STYLE);

        self::assertStringContainsString('[OUTPUT STYLE CONTROL MODULE]', $fragment);
        self::assertStringContainsString('- style: detailed', $fragment);
        self::assertStringNotContainsString('{{', $fragment);
    }
}
