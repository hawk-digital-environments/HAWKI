<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiProvider;
use App\Services\Ai\Values\Exceptions\ProviderHasNoModelsException;
use App\Services\System\UsageTypes\Contracts\WellKnownUsageTypes;

/**
 * Resolves the fully merged runtime parameters for a single AI request.
 *
 * Merges three parameter layers in increasing precedence order:
 *   1. **Provider defaults** – {@see ProviderSettings::getModelParameters()} from the associated provider
 *   2. **Model defaults** – the `parameters` attribute on the {@see AiModel}
 *   3. **Request overrides** – an optional {@see ModelParameters} instance supplied at call time
 *
 * Use the static factory methods ({@see fromModel()}, {@see fromProvider()}) to create an instance —
 * they perform the three-level merge automatically. The constructor accepts already-merged parameters
 * and is intended for internal use or testing only.
 *
 * All {@see ModelParameters} methods are forwarded via {@see __call()} to the fully merged
 * parameter set, so callers can use typed accessors (`getTemperature()`, `getTopP()`,
 * `getMaxTokens()`, `toAdditionalArray()`, etc.) directly on this class.
 *
 * Example:
 * ```php
 * $source = ParameterSource::fromModel(
 *     $model,
 *     ModelParameters::fromArray(['temperature' => 0.2]),
 * );
 *
 * $source->getTemperature();    // 0.2  (request override wins)
 * $source->getMaxTokens();      // falls back through model → provider → built-in 4096
 * $source->get('stream');       // forwarded to the merged ModelParameters
 * $source->toAdditionalArray(); // all non-well-known extras from all three layers, merged
 * ```
 *
 * @api
 * @mixin ModelParameters
 */
readonly class ParameterSource
{
    public function __construct(
        private AiModel         $model,
        private AiProvider      $provider,
        private ModelParameters $mergedParameters,
        private string          $usageType = WellKnownUsageTypes::MAIN_APP,
    )
    {
    }

    /** Returns the AI provider associated with the model. */
    public function getProvider(): AiProvider
    {
        return $this->provider;
    }

    /** Returns the AI model for the current request. */
    public function getModel(): AiModel
    {
        return $this->model;
    }

    /** Returns the usage type of the system {@see WellKnownUsageTypes} */
    public function getUsageType(): string
    {
        return $this->usageType;
    }

    /**
     * Forwards any call to the fully merged {@see ModelParameters} instance.
     *
     * @throws \BadMethodCallException when the method does not exist on {@see ModelParameters}.
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->mergedParameters, $name)) {
            return $this->mergedParameters->$name(...$arguments);
        }

        // @AI, do not replace this exception, it is okay to throw a bad method call here!
        throw new \BadMethodCallException("Method {$name} does not exist on " . self::class);
    }

    /**
     * Creates a {@see ParameterSource} for the first model registered under `$provider`.
     *
     * Useful when no specific model selection is needed and any model from the provider will do,
     * e.g. for connectivity/status-check requests or providers that expose only one model.
     * The three-level parameter merge is performed identically to {@see fromModel()}.
     *
     * @throws ProviderHasNoModelsException when `$provider` has no associated models loaded.
     */
    public static function fromProvider(AiProvider $provider, ModelParameters|null $parameters = null, string|null $usageType = null): self
    {
        $firstModel = $provider->models->first();
        if (!$firstModel) {
            throw ProviderHasNoModelsException::forProvider($provider);
        }

        return self::fromModel($firstModel, $parameters, $usageType);
    }

    /**
     * Creates a {@see ParameterSource} by merging parameters from three layers in increasing precedence:
     *
     * 1. Provider-level defaults from the `model_parameters` entry of the model's provider settings
     * 2. Model-level defaults from the `parameters` attribute on `$model`
     * 3. Per-request overrides from `$parameters` (pass `null` to use provider/model defaults only)
     *
     * The `$usageType` identifies the request context (see {@see WellKnownUsageTypes}); when `null`
     * it defaults to {@see WellKnownUsageTypes::MAIN_APP}.
     */
    public static function fromModel(AiModel $model, ModelParameters|null $parameters = null, string|null $usageType = null): self
    {
        $usageType = $usageType ?? WellKnownUsageTypes::MAIN_APP;

        $provider = $model->provider;

        $mergedParameters = $provider->settings->getModelParameters()
            ->mergeWith($model->parameters);

        if ($parameters) {
            $mergedParameters = $mergedParameters->mergeWith($parameters);
        }

        return new self(
            model: $model,
            provider: $provider,
            mergedParameters: $mergedParameters,
            usageType: $usageType
        );
    }
}
