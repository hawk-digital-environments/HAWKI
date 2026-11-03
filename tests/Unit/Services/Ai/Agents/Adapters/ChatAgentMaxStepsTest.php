<?php
declare(strict_types=1);


namespace Tests\Unit\Services\Ai\Agents\Adapters;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent;
use App\Services\Ai\Agents\Implementations\Chat\ChatAgent;
use App\Services\Ai\Agents\Values\AgentRequestContext;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Settings\Values\AiModelSettings;
use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Ai\Tools\Implementations\TestTool;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Providers\Provider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Log\LoggerInterface;
use Tests\TestCase;

/**
 * Pins the wiring between the per-model `max_tool_calling_rounds` setting and
 * the SDK generation loop's step budget.
 *
 * `TextGenerationOptions::forAgent()` resolves `maxSteps` from an agent's
 * `maxSteps(): ?int` method. Without that hook the loop falls back to its
 * `round(1.5 x tool count)` heuristic and the declared model guardrail is
 * never consumed (TOOL_CALL_BUG.md, R1).
 */
#[CoversClass(ChatAgent::class)]
#[CoversClass(TextGenerationOptions::class)]
class ChatAgentMaxStepsTest extends TestCase
{
    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds real model settings whose setting registry is the booted app's
     * (the ServiceLocatorTrait disables container fallback under PHPUnit, so the
     * registry has to be injected explicitly for registry defaults to apply).
     */
    private function makeSettings(array $explicit = []): AiModelSettings
    {
        $settings = AiModelSettings::fromArray($explicit);
        $settings->setService(
            \App\Services\Ai\Models\Settings\AiModelSettingRegistry::class,
            $this->app->make(\App\Services\Ai\Models\Settings\AiModelSettingRegistry::class)
        );

        return $settings;
    }

    private function makeAgent(AiModelSettings $settings, iterable $tools = []): ChatAgent
    {
        $model = $this->createMock(AiModel::class);
        // AiModel exposes cast properties via Eloquent's magic __get
        $model->method('__get')->willReturnCallback(
            fn(string $key) => $key === 'settings' ? $settings : null
        );

        return new ChatAgent(
            context: new AgentRequestContext(
                provider: new AiProviderProxy(
                    provider: $this->createMock(AiProvider::class),
                    adapter: $this->createMock(ProviderAdapterInterface::class),
                    driver: $this->createMock(Provider::class),
                ),
                model: $model,
                modelParameters: new AiModelParameters(),
            ),
            instructions: 'You are a test agent.',
            messages: [new UserMessage('Hello!')],
            tools: $tools,
        );
    }

    // =========================================================================
    // Tests
    // =========================================================================

    public function testItExposesTheModelRoundLimitAsTheSdkStepBudget(): void
    {
        // No explicit limit: the registry default (5) applies.
        $agent = $this->makeAgent($this->makeSettings());

        static::assertSame(
            5,
            TextGenerationOptions::forAgent($agent)->maxSteps
        );
    }

    public function testItTreatsAZeroRoundLimitAsOff(): void
    {
        // An explicit zero means "off": unlimited model generations, bounded only by
        // the per-tool call ceilings. The SDK heuristic is deliberately unreachable.
        $agent = $this->makeAgent($this->makeSettings([
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => 0,
        ]));

        static::assertSame(
            AbstractTextGeneratingAgent::UNLIMITED_STEPS,
            TextGenerationOptions::forAgent($agent)->maxSteps
        );
    }

    public function testItReturnsAModelSpecificRoundLimitOverride(): void
    {
        $agent = $this->makeAgent($this->makeSettings([
            WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => 7,
        ]));

        static::assertSame(7, TextGenerationOptions::forAgent($agent)->maxSteps);
    }

    public function testItDoesNotLetToolCeilingsInfluenceTheStepBudget(): void
    {
        // The step budget comes from the model setting alone; a tool's own ceiling
        // (TestTool declares 1 run) is enforced separately per tool.
        $agent = $this->makeAgent(
            $this->makeSettings(),
            [new TestTool($this->createMock(LoggerInterface::class))]
        );

        static::assertSame(5, TextGenerationOptions::forAgent($agent)->maxSteps);
    }
}
