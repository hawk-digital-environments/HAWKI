<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing\Values\Chat;


use App\Services\Ai\Exceptions\InvalidPricingRangeException;
use App\Services\Ai\Models\Pricing\AiModelPricingInterface;
use Illuminate\Support\Collection;

/**
 * Pricing information for a chat-type AI model.
 *
 * Most LLM providers use tiered pricing where the cost per token changes above certain
 * token-count thresholds. This class stores two independent sets of pricing ranges —
 * a standard set and an optional "priority" set (for guaranteed-capacity tiers offered
 * by some providers). Each set is an array of non-overlapping {@see ChatPricingRange} objects.
 *
 * Three states are distinguished:
 * - **Undefined** ({@see isUndefined()}): both range sets are null — no pricing data yet.
 * - **Free** ({@see isFree()}): both sets are non-null but empty — the model is known to be free.
 * - **Priced**: at least one range set has entries.
 *
 * Serialised to/from the `pricing` JSON column of {@see \App\Models\Ai\AiModel}
 * via {@see \App\Casts\AsInstance}.
 */
class ChatAiModelPricing implements AiModelPricingInterface
{
    public function __construct(
        /** @var array<ChatPricingRange>|null */
        private array|null $ranges,
        /** @var array<ChatPricingRange>|null */
        private array|null $priorityRanges
    )
    {
    }

    /** Returns true when no pricing data has been set (both range sets are null). */
    public function isUndefined(): bool
    {
        return $this->ranges === null && $this->priorityRanges === null;
    }

    /** Returns true when pricing is explicitly known to be free (both sets non-null but empty). */
    public function isFree(): bool
    {
        return $this->ranges !== null && $this->priorityRanges !== null &&
            empty($this->ranges) && empty($this->priorityRanges);
    }

    /**
     * Returns true when the given range set has at least one entry.
     *
     * @param bool|null $priority Pass true to check the priority range set; false/null for standard.
     */
    public function hasRanges(bool|null $priority = null): bool
    {
        if ($priority === true) {
            return !empty($this->priorityRanges);
        }
        return !empty($this->ranges);
    }

    /**
     * Adds a range to the standard (or priority) set.
     *
     * Enforces two invariants: a total range (0–∞) cannot coexist with bounded ranges,
     * and no two ranges may overlap. Throws {@see InvalidPricingRangeException} on violation.
     *
     * @param bool|null $priority Pass true to add to the priority range set.
     */
    public function addRange(ChatPricingRange $range, bool|null $priority = null): void
    {
        $priority = $priority ?? false;
        $ranges = $priority ? $this->priorityRanges : $this->ranges;
        if (count($ranges) >= 1 && $range->isTotalRange()) {
            throw InvalidPricingRangeException::totalRangeWithExistingRanges();
        }
        $hasTotalRange = collect($ranges)->contains(fn(ChatPricingRange $key) => $key->isTotalRange());
        if ($hasTotalRange) {
            throw InvalidPricingRangeException::withExistingTotalRange();
        }

        // Ensure that there is no overlap with existing ranges
        foreach ($ranges as $existingRange) {
            if ($existingRange->overlapsWith($range)) {
                throw InvalidPricingRangeException::overlappingWithExistingRange();
            }
        }

        $ranges[$range->toHash()] = $range;
        ksort($ranges);
        if ($priority) {
            $this->priorityRanges = $ranges;
        } else {
            $this->ranges = $ranges;
        }
    }

    /**
     * Returns all ranges in the given set as a flat, re-indexed array.
     *
     * @param bool|null $priority Pass true to retrieve the priority range set.
     * @return array<ChatPricingRange>
     */
    public function getRanges(bool|null $priority = null): array
    {
        if ($priority === true) {
            return array_values($this->priorityRanges ?? []);
        }
        return array_values($this->ranges ?? []);
    }

    /**
     * Replaces all ranges in the set. Pass null to reset to the undefined state.
     *
     * @param array<ChatPricingRange>|null $ranges
     * @param bool|null $priority Pass true to target the priority range set.
     */
    public function setRanges(array|null $ranges, bool|null $priority = null): void
    {
        $this->flushRanges($priority);
        if ($ranges !== null) {
            foreach ($ranges as $range) {
                $this->addRange($range, $priority);
            }
        }
    }

    /** Removes a specific range by its hash key. No-op when the range is not present. */
    public function removeRange(ChatPricingRange $range, bool|null $priority = null): void
    {
        $priority = $priority ?? false;
        if ($priority) {
            unset($this->priorityRanges[$range->toHash()]);
        } else {
            unset($this->ranges[$range->toHash()]);
        }
    }

    /** Removes all ranges from the set, leaving it as an empty array (free/zero-cost state). */
    public function flushRanges(bool|null $priority = null): void
    {
        if ($priority === true) {
            $this->priorityRanges = [];
        } else {
            $this->ranges = [];
        }
    }

    /**
     * Returns the lowest input-token cost across all ranges, or null when no pricing data exists.
     * Returns 0.0 for free models ({@see isFree()}).
     */
    public function getCheapestPricePerInputToken(bool|null $priority = null): float|null
    {
        if ($this->isFree()) {
            return 0.0;
        }

        return $this->getRangesSortedBy(
            $priority,
            fn(ChatPricingRange $range) => $range->inputCostPerToken)?->first()->inputCostPerToken;
    }

    /**
     * Returns the lowest output-token cost across all ranges, or null when no pricing data exists.
     * Returns 0.0 for free models.
     */
    public function getCheapestPricePerOutputToken(bool|null $priority = null): float|null
    {
        if ($this->isFree()) {
            return 0.0;
        }

        return $this->getRangesSortedBy(
            $priority,
            fn(ChatPricingRange $range) => $range->outputCostPerToken)?->first()->outputCostPerToken;
    }

    /**
     * Returns the highest input-token cost across all ranges, or null when no pricing data exists.
     * Returns 0.0 for free models.
     */
    public function getMostExpensivePricePerInputToken(bool|null $priority = null): float|null
    {
        if ($this->isFree()) {
            return 0.0;
        }

        return $this->getRangesSortedBy(
            $priority,
            fn(ChatPricingRange $range) => $range->inputCostPerToken)?->last()->inputCostPerToken;
    }

    /**
     * Returns the highest output-token cost across all ranges, or null when no pricing data exists.
     * Returns 0.0 for free models.
     */
    public function getMostExpensivePricePerOutputToken(bool|null $priority = null): float|null
    {
        if ($this->isFree()) {
            return 0.0;
        }

        return $this->getRangesSortedBy(
            $priority,
            fn(ChatPricingRange $range) => $range->outputCostPerToken)?->last()->outputCostPerToken;
    }

    private function getRangesSortedBy(
        bool|null $priority,
        \Closure  $sortBy
    ): Collection|null
    {
        $ranges = $this->getRanges($priority);
        if (empty($ranges)) {
            return null;
        }
        return collect($ranges)
            ->sortBy($sortBy);
    }


    public static function fromArray(array $data): static
    {
        $rangesRaw = $data['ranges'] ?? null;
        $priorityRangesRaw = $data['priority_ranges'] ?? null;
        $ranges = $rangesRaw !== null
            ? array_map(fn(array $rangeData) => ChatPricingRange::fromArray($rangeData), $rangesRaw)
            : null;
        $priorityRanges = $priorityRangesRaw !== null
            ? array_map(fn(array $rangeData) => ChatPricingRange::fromArray($rangeData), $priorityRangesRaw)
            : null;
        /** @phpstan-ignore new.static */
        return new static($ranges, $priorityRanges);
    }

    public function toArray(): array
    {
        if ($this->isUndefined()) {
            return [];
        }

        return [
            'ranges' => $this->ranges !== null
                ? array_values(array_map(fn(ChatPricingRange $range) => $range->toArray(), $this->ranges))
                : null,
            'priority_ranges' => $this->priorityRanges !== null
                ? array_values(array_map(fn(ChatPricingRange $range) => $range->toArray(), $this->priorityRanges))
                : null,
        ];
    }
}
