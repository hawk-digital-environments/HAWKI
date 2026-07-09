<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Pricing;


use App\Models\Ai\AiModel;
use Illuminate\Container\Attributes\Singleton;

/**
 * Maps model types to their corresponding pricing implementation classes.
 *
 * Different model types use different pricing structures (chat models price by token,
 * image models by resolution, etc.). The registry maps each model-type string to the
 * class implementing {@see AiModelPricingInterface} for that type, allowing the cast
 * layer to instantiate the correct class when hydrating a model from the database.
 *
 * Built-in registrations are in {@see \App\Providers\AiServiceProvider}.
 *
 * @api
 */
#[Singleton]
class AiModelPricingRegistry
{
    private array $pricingClasses = [];

    /**
     * Registers a pricing class for a model type.
     *
     * @param string $pricingClass Must implement {@see AiModelPricingInterface}.
     */
    public function declare(
        string $modelType,
        string $pricingClass
    ): self
    {
        $this->pricingClasses[$modelType] = $pricingClass;
        return $this;
    }

    /** Returns the pricing class name for $modelType, or null when none is registered. */
    public function getPricingClass(string $modelType): ?string
    {
        return $this->pricingClasses[$modelType] ?? null;
    }

    /** Convenience wrapper: reads the model type from $model and delegates to {@see getPricingClass()}. */
    public function getPricingClassForModel(AiModel $model): ?string
    {
        return $this->getPricingClass($model->model_type ?? '');
    }
}
