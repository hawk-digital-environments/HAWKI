<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing\Values;


use App\Services\Ai\Models\Pricing\AiModelPricingInterface;

/**
 * Null-object implementation of {@see AiModelPricingInterface}.
 *
 * Returned by default when no pricing class has been registered for the model type
 * in {@see \App\Services\Ai\Models\Pricing\AiModelPricingRegistry}, or when the model
 * record contains no pricing data. Prevents null checks at call sites and makes the
 * "no pricing data yet" state explicit.
 *
 * Semantics:
 * - {@see isUndefined()} returns true — pricing is unknown, not free.
 * - {@see isFree()} returns false — absence of data must not be misread as zero cost.
 *
 * The enrichment helpers in {@see \App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait}
 * detect this type and replace it with the appropriate typed pricing object (e.g.
 * {@see \App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing}) before writing
 * enriched values.
 */
final class NullPricing implements AiModelPricingInterface
{
    /** Always true: this object represents the absence of pricing data. */
    public function isUndefined(): bool
    {
        return true;
    }

    /**
     * Always false: an unknown price must not be treated as free.
     *
     * Only {@see \App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing::isFree()}
     * returns true when pricing is explicitly known to be zero.
     */
    public function isFree(): bool
    {
        return false;
    }

    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
