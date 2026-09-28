<?php

declare(strict_types=1);

namespace App\Services\Ai\Providers\Adapters\Values;

/**
 * Anthropic extended-thinking request configuration shared by the Anthropic and
 * AWS Bedrock adapters (Bedrock runs the same Claude thinking feature in a
 * different request envelope).
 *
 * Thinking tokens count towards the request's output limit, so the budget is
 * capped at half of the effective max-tokens. Anthropic requires a thinking
 * budget of at least 1,024 tokens and rejects most sampling values while
 * thinking is enabled. Requests that cannot satisfy these constraints keep
 * thinking disabled, and every deviation from the user-configured parameters
 * (disabled thinking, neutralised sampling) is reported as a warning so callers
 * can surface it in their logs instead of silently overriding user input.
 *
 * @see https://docs.anthropic.com/en/docs/build-with-claude/extended-thinking
 */
final readonly class AnthropicThinkingConfig
{
    public const int MIN_BUDGET_TOKENS = 1_024;

    /**
     * @param list<string> $warnings
     */
    private function __construct(
        public bool $enabled,
        public array $thinking,
        public array $samplingOverrides,
        public bool $overridesSampling,
        public array $warnings,
    ) {
    }

    /**
     * @param null|float $temperature user-configured sampling temperature, null when unset
     * @param null|float $topP        user-configured sampling top-p, null when unset
     */
    public static function from(
        int $requestedBudgetTokens,
        int $maxTokens,
        ?float $temperature,
        ?float $topP,
    ): self {
        $warnings = [];

        if (self::MIN_BUDGET_TOKENS > $requestedBudgetTokens) {
            $warnings[] = \sprintf(
                'Extended thinking disabled: the requested thinking budget of %d tokens is below Anthropic\'s minimum of %d tokens.',
                $requestedBudgetTokens,
                self::MIN_BUDGET_TOKENS,
            );

            return new self(false, [], [], false, $warnings);
        }

        $budgetTokens = min($requestedBudgetTokens, intdiv($maxTokens, 2));

        if (self::MIN_BUDGET_TOKENS > $budgetTokens) {
            $warnings[] = \sprintf(
                'Extended thinking disabled: half of the %d max output tokens (%d) is below Anthropic\'s minimum thinking budget of %d tokens.',
                $maxTokens,
                $budgetTokens,
                self::MIN_BUDGET_TOKENS,
            );

            return new self(false, [], [], false, $warnings);
        }

        $samplingOverrides = [];

        if (null !== $temperature) {
            $samplingOverrides['temperature'] = 1.0;

            if (1.0 !== $temperature) {
                $warnings[] = \sprintf(
                    'Sampling temperature %.2f neutralised to 1.0: Anthropic rejects other values while extended thinking is enabled.',
                    $temperature,
                );
            }
        }

        if (null !== $topP && 0.95 > $topP) {
            $samplingOverrides['top_p'] = 1.0;
            $warnings[] = \sprintf(
                'Sampling top-p %.2f neutralised to 1.0: Anthropic rejects lower values while extended thinking is enabled.',
                $topP,
            );
        }

        return new self(
            true,
            [
                'type' => 'enabled',
                'budget_tokens' => $budgetTokens,
            ],
            $samplingOverrides,
            null !== $temperature || null !== $topP,
            $warnings,
        );
    }
}
