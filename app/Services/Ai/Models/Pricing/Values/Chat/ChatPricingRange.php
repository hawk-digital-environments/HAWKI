<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing\Values\Chat;

use App\Casts\Contracts\CastableInstanceInterface;

/**
 * A single pricing tier for a chat model, covering a bounded token-count range.
 *
 * The range is half-open: $rangeStart (inclusive) to $rangeEnd (exclusive). A "total
 * range" — $rangeStart = 0 and $rangeEnd = PHP_INT_MAX — represents flat-rate pricing
 * and cannot coexist with bounded ranges in the same {@see ChatAiModelPricing} set.
 *
 * All cost values are per-token amounts in $currency. Null indicates that the provider
 * does not publish that cost component.
 */
class ChatPricingRange implements CastableInstanceInterface
{
    public const string CURRENCY_USD = 'USD';
    public const string CURRENCY_EUR = 'EUR';

    public function __construct(
        public string     $currency,
        public float|null $inputCostPerToken,
        public float|null $inputCostPerCachedToken,
        public float|null $outputCostPerToken,
        public float|null $outputCostPerReasoningToken,
        public int        $rangeStart = 0,
        public int        $rangeEnd = PHP_INT_MAX
    )
    {
    }

    /** Returns true when the pricing currency is EUR. */
    public function isCurrencyEuro(): bool
    {
        return $this->currency === self::CURRENCY_EUR;
    }

    /** Returns true when the pricing currency is USD. */
    public function isCurrencyUsd(): bool
    {
        return $this->currency === self::CURRENCY_USD;
    }

    /** Returns true when this range covers all token counts (0–∞), i.e. flat-rate pricing. */
    public function isTotalRange(): bool
    {
        return $this->rangeStart === 0 && $this->rangeEnd === PHP_INT_MAX;
    }

    /**
     * Returns true when this range overlaps with $other.
     *
     * Used by {@see ChatAiModelPricing::addRange()} to enforce non-overlapping ranges.
     */
    public function overlapsWith(self $other): bool
    {
        return $this->rangeStart < $other->rangeEnd && $this->rangeEnd > $other->rangeStart;
    }

    /**
     * Returns a deterministic string key that uniquely identifies this range by its boundaries
     * and cost values. Used as the array key within the parent {@see ChatAiModelPricing} set.
     */
    public function toHash(): string
    {
        $range = [
            $this->rangeStart,
            $this->rangeEnd
        ];
        if ($this->isTotalRange()) {
            $range = ['total'];
        }

        return implode('-', [
            ...$range,
            $this->currency,
            $this->inputCostPerToken,
            $this->inputCostPerCachedToken,
            $this->outputCostPerToken,
            $this->outputCostPerReasoningToken
        ]);
    }

    public function toArray(): array
    {
        return [
            'currency' => $this->currency,
            'input_cost_per_token' => $this->inputCostPerToken,
            'input_cost_per_cached_token' => $this->inputCostPerCachedToken,
            'output_cost_per_token' => $this->outputCostPerToken,
            'output_cost_per_reasoning_token' => $this->outputCostPerReasoningToken,
            'range' => [
                $this->rangeStart,
                $this->rangeEnd === PHP_INT_MAX ? null : $this->rangeEnd
            ]
        ];
    }

    public static function fromArray(array $data): static
    {
        return new self(
            currency: $data['currency'] ?? self::CURRENCY_USD,
            inputCostPerToken: $data['input_cost_per_token'] ?? null,
            inputCostPerCachedToken: $data['input_cost_per_cached_token'] ?? null,
            outputCostPerToken: $data['output_cost_per_token'] ?? null,
            outputCostPerReasoningToken: $data['output_cost_per_reasoning_token'] ?? null,
            rangeStart: $data['range'][0] ?? 0,
            rangeEnd: $data['range'][1] ?? PHP_INT_MAX
        );
    }
}
