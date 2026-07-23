<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing;


use App\Casts\Contracts\CastableInstanceInterface;

/**
 * Marker interface for model pricing implementations.
 *
 * Pricing describes the cost structure of a model. Implementations are registered
 * per model type in {@see AiModelPricingRegistry} and are serialised to/from the
 * `pricing` JSON column of {@see \App\Models\Ai\AiModel} via the
 * {@see \App\Casts\AsInstance} cast.
 */
interface AiModelPricingInterface extends CastableInstanceInterface
{
    /** Returns true when no pricing data has been set. */
    public function isUndefined(): bool;

    /** Returns true when pricing is explicitly known to be free. */
    public function isFree(): bool;
}
