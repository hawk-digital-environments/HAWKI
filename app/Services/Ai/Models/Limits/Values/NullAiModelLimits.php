<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Limits\Values;


use App\Services\Ai\Models\Limits\AiModelLimitsInterface;

/**
 * Null-object implementation of {@see AiModelLimitsInterface}.
 *
 * Used as the default limits value for model types whose limit class has not been
 * registered in {@see \App\Services\Ai\Models\Limits\AiModelLimitRegistry}, or when
 * the model record has no limits data in the database. Returning this object rather
 * than null avoids null checks at every call site and signals explicitly that no
 * limit information is available.
 *
 * The enrichment helpers in {@see \App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait}
 * detect this type and replace it with the appropriate typed limits object (e.g.
 * {@see ChatAiModelLimits}) before writing enriched values.
 */
final class NullAiModelLimits implements AiModelLimitsInterface
{
    public static function fromArray(array $data): static
    {
        return new self();
    }

    public function toArray(): array
    {
        return [];
    }
}
