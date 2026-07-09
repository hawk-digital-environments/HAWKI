<?php
declare(strict_types=1);

namespace Tests\Unit\Services\Ai\Values;

use App\Models\Ai\AiModel;
use App\Services\Ai\Values\TokenUsage;
use Laravel\Ai\Responses\Data\Usage;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(TokenUsage::class)]
class TokenUsageTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    private function makeModel(string $modelId = 'gpt-4o'): AiModel
    {
        $model = new AiModel();
        $model->model_id = $modelId;
        return $model;
    }

    // =========================================================================
    // Construction
    // =========================================================================

    public function testItConstructs(): void
    {
        $model = $this->makeModel();
        $sut = new TokenUsage(model: $model, promptTokens: 10, completionTokens: 20);
        static::assertInstanceOf(TokenUsage::class, $sut);
    }

    // =========================================================================
    // fromLaravelUsage
    // =========================================================================

    public function testItFromLaravelUsageMapsPromptTokens(): void
    {
        $model = $this->makeModel();
        $usage = new Usage(promptTokens: 100, completionTokens: 50, reasoningTokens: 0);

        $sut = TokenUsage::fromLaravelUsage($usage, $model);

        static::assertSame(100, $sut->promptTokens);
    }

    public function testItFromLaravelUsageSumsCompletionAndReasoningTokens(): void
    {
        $model = $this->makeModel();
        $usage = new Usage(promptTokens: 10, completionTokens: 40, reasoningTokens: 15);

        $sut = TokenUsage::fromLaravelUsage($usage, $model);

        // Reasoning tokens are folded into completionTokens
        static::assertSame(55, $sut->completionTokens);
    }

    public function testItFromLaravelUsageAttachesModel(): void
    {
        $model = $this->makeModel('claude-3-5-sonnet');
        $usage = new Usage();

        $sut = TokenUsage::fromLaravelUsage($usage, $model);

        static::assertSame($model, $sut->model);
    }

    public function testItFromLaravelUsageWithZeroReasoningTokens(): void
    {
        $model = $this->makeModel();
        $usage = new Usage(promptTokens: 5, completionTokens: 30, reasoningTokens: 0);

        $sut = TokenUsage::fromLaravelUsage($usage, $model);

        static::assertSame(30, $sut->completionTokens);
    }

    // =========================================================================
    // toArray
    // =========================================================================

    public function testItToArrayContainsModelId(): void
    {
        $model = $this->makeModel('gpt-4o');
        $sut = new TokenUsage(model: $model, promptTokens: 10, completionTokens: 20);

        static::assertSame('gpt-4o', $sut->toArray()['model']);
    }

    public function testItToArrayContainsPromptTokens(): void
    {
        $model = $this->makeModel();
        $sut = new TokenUsage(model: $model, promptTokens: 42, completionTokens: 0);

        static::assertSame(42, $sut->toArray()['prompt_tokens']);
    }

    public function testItToArrayContainsCompletionTokens(): void
    {
        $model = $this->makeModel();
        $sut = new TokenUsage(model: $model, promptTokens: 0, completionTokens: 99);

        static::assertSame(99, $sut->toArray()['completion_tokens']);
    }

    // =========================================================================
    // jsonSerialize
    // =========================================================================

    public function testItJsonSerializeMatchesToArray(): void
    {
        $model = $this->makeModel('gpt-4o');
        $sut = new TokenUsage(model: $model, promptTokens: 7, completionTokens: 13);

        static::assertSame($sut->toArray(), $sut->jsonSerialize());
    }

    public function testItIsJsonEncodable(): void
    {
        $model = $this->makeModel('gpt-4o');
        $sut = new TokenUsage(model: $model, promptTokens: 7, completionTokens: 13);

        $json = json_encode($sut);
        static::assertIsString($json);
        $decoded = json_decode($json, true);
        static::assertSame('gpt-4o', $decoded['model']);
        static::assertSame(7, $decoded['prompt_tokens']);
        static::assertSame(13, $decoded['completion_tokens']);
    }
}
