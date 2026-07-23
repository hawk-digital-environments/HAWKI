<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Limits;


use App\Models\Ai\AiModel;
use Illuminate\Container\Attributes\Singleton;

/**
 * Maps model types to their corresponding limit implementation classes.
 *
 * Different model types impose different kinds of limits (chat models have token caps,
 * image models have resolution caps, etc.). The registry maps each model-type string to
 * the class implementing {@see AiModelLimitsInterface} for that type, allowing the cast
 * layer to instantiate the correct class when hydrating a model from the database.
 *
 * Built-in registrations are in {@see \App\Providers\AiServiceProvider}.
 *
 * @see AiModelLimitsInterface
 * @api
 */
#[Singleton]
class AiModelLimitRegistry
{
    private array $limitClasses = [];

    /**
     * Registers a limits class for a model type.
     *
     * @param string $limitClass Must implement {@see AiModelLimitsInterface}.
     */
    public function declare(
        string $modelType,
        string $limitClass
    ): self
    {
        $this->limitClasses[$modelType] = $limitClass;
        return $this;
    }

    /** Returns the limits class name for $modelType, or null when none is registered. */
    public function getLimitClass(string $modelType): ?string
    {
        return $this->limitClasses[$modelType] ?? null;
    }

    /** Convenience wrapper: reads the model type from $model and delegates to {@see getLimitClass()}. */
    public function getLimitClassForModel(AiModel $model): ?string
    {
        return $this->getLimitClass($model->model_type ?? '');
    }
}
