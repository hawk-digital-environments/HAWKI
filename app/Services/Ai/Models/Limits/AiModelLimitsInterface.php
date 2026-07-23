<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Limits;


use App\Casts\Contracts\CastableInstanceInterface;

/**
 * Marker interface for model limit implementations.
 *
 * Limits describe the operational boundaries of a model (e.g. maximum input/output tokens
 * for a chat model). Implementations are registered per model type in
 * {@see AiModelLimitRegistry} and are serialised to/from the `limits` JSON column of
 * {@see \App\Models\Ai\AiModel} via the {@see \App\Casts\AsInstance} cast.
 */
interface AiModelLimitsInterface extends CastableInstanceInterface
{
}
