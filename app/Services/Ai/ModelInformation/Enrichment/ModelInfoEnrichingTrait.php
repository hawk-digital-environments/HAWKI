<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment;


use App\Models\Ai\AiModel;
use App\Models\Ai\AiModelDescription;
use App\Services\Ai\Exceptions\InvalidModelEnrichmentTypeException;
use App\Services\Ai\Models\Capabilities\Values\NativeAiModelCapabilities;
use App\Services\Ai\Models\Flags\Values\AiModelFlags;
use App\Services\Ai\Models\Io\Values\AiModelIoMethods;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\Limits\Values\NullAiModelLimits;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Pricing\Values\NullPricing;
use App\Services\Ai\Providers\Values\AiProviderProxy;
use App\Services\Translation\Value\Locale;

/**
 * Helper methods for {@see ModelInfoEnricherInterface} implementations.
 *
 * All methods follow a "fill-in-the-blank" strategy: they write values only when
 * the corresponding field on the model is not yet populated, so earlier enrichers'
 * data is never overwritten by later ones. Use these inside
 * {@see ModelInfoEnricherInterface::enrichModelInfo()}.
 */
trait ModelInfoEnrichingTrait
{
    /**
     * Creates a bare {@see AiModel} instance with the minimum required fields set.
     *
     * The model is not persisted; it exists only as a value object during enrichment.
     */
    protected function createNewModelInfo(
        string          $modelId,
        AiProviderProxy $provider,
        string|null     $modelType = null
    ): AiModel
    {
        return new AiModel([
            'model_id' => $modelId,
            'provider_id' => $provider->id,
            'model_type' => $modelType
        ]);
    }

    /**
     * Shorthand for {@see createNewModelInfo()} with model_type pre-set to {@see WellKnownModelTypes::CHAT}.
     */
    protected function createNewChatModelInfo(
        string          $modelId,
        AiProviderProxy $provider
    ): AiModel
    {
        return $this->createNewModelInfo($modelId, $provider, WellKnownModelTypes::CHAT);
    }

    /** Sets the model's display label only when it is not already populated. */
    protected function enrichLabel(AiModel $model, string|null $label): void
    {
        if (!$label) {
            return;
        }

        if (empty($model->label)) {
            $model->label = $label;
        }
    }

    /**
     * Merges the given modalities into the model's input set.
     *
     * Accepts a single string, an array of strings, or an {@see AiModelIoMethods} instance.
     * New modalities are appended; existing ones are preserved unchanged.
     */
    protected function enrichInput(AiModel $model, string|array|AiModelIoMethods|null $input): void
    {
        self::enrichIoMethods('input', $model, $input);
    }

    /**
     * Merges the given modalities into the model's output set.
     *
     * Accepts a single string, an array of strings, or an {@see AiModelIoMethods} instance.
     * New modalities are appended; existing ones are preserved unchanged.
     */
    protected function enrichOutput(AiModel $model, string|array|AiModelIoMethods|null $output): void
    {
        self::enrichIoMethods('output', $model, $output);
    }

    /** Sets the model type (normalised to lowercase) only when a non-empty value is provided. */
    protected function enrichModelType(AiModel $model, string|null $modelType): void
    {
        $normalized = strtolower(trim($modelType ?? ''));

        if (empty($normalized)) {
            return;
        }

        $model->model_type = $normalized;
    }

    /**
     * Adds a localised description to the model when none exists for the given locale.
     *
     * If the model already has a description in $locale, the call is a no-op.
     */
    protected function attachDescription(AiModel $model, Locale $locale, string|null $description): void
    {
        if (empty($description)) {
            return;
        }

        $descriptions = $model->description;
        if ($descriptions->contains(fn(AiModelDescription $desc) => $desc->locale->lang === $locale->lang)) {
            return;
        }

        $newDescription = new AiModelDescription([
            'locale' => $locale->lang,
            'description' => $description
        ]);

        $model->description->add($newDescription);
    }

    /**
     * Adds all localised descriptions to the model, skipping any locales that already exist.
     *
     * Each entry in $descriptions must be an {@see AiModelDescription} instance; any other
     * values are ignored. If $descriptions is null or empty, the call is a no-op.
     *
     * @param AiModelDescription[]|null $descriptions
     */
    protected function attachAllDescriptions(AiModel $model, array|null $descriptions): void
    {
        if (empty($descriptions)) {
            return;
        }

        foreach ($descriptions as $description) {
            $this->attachDescription($model, $description->locale, $description->description);
        }
    }

    /**
     * Merges the given flags into the model's flag set, deduplicating.
     *
     * The new flags are merged with any already on the model; existing flags take
     * precedence on collision (both are strings, so duplicates are simply dropped).
     */
    protected function attachFlags(AiModel $model, array|null $flags): void
    {
        if (empty($flags)) {
            return;
        }

        $existingFlags = $model->flags->toArray();
        $mergedFlags = array_unique(array_merge($flags, $existingFlags));
        $model->flags = AiModelFlags::fromArray($mergedFlags);
    }

    /**
     * Merges inference parameters into the model's parameter set.
     *
     * Values already on the model take precedence over $parameters, so this method
     * only fills in keys that have not been set by an earlier enricher.
     */
    protected function enrichParameters(AiModel $model, array|null $parameters): void
    {
        if (empty($parameters)) {
            return;
        }

        $existingParameters = $model->parameters->toArray();
        $mergedParameters = array_merge($parameters, $existingParameters);
        $model->parameters = AiModelParameters::fromArray($mergedParameters);
    }

    /**
     * Sets token limits on a chat model, only for limit fields that are not yet populated.
     *
     * Either value may be null to skip that direction. Throws when the model's limits
     * object is not a {@see ChatAiModelLimits} instance — this typically means the model
     * type has not been set to chat before calling this method.
     */
    protected function enrichChatLimits(
        AiModel                    $model,
        int|null|ChatAiModelLimits $maxInputTokens,
        int|null                   $maxOutputTokens = null
    ): void
    {
        if ($maxInputTokens === null && $maxOutputTokens === null) {
            return;
        }

        $limits = $model->limits;
        if ($limits instanceof NullAiModelLimits) {
            $model->limits = $limits = ChatAiModelLimits::fromArray([]);
        } else if (!$limits instanceof ChatAiModelLimits) {
            throw InvalidModelEnrichmentTypeException::forInvalidLimitsType((string) $model->model_id, get_debug_type($limits));
        }

        if ($maxInputTokens instanceof ChatAiModelLimits) {
            $maxOutputTokens = $maxInputTokens->getMaxOutputTokens();
            $maxInputTokens = $maxInputTokens->getMaxInputTokens();
        }

        if ($maxInputTokens !== null && $limits->getMaxInputTokens() === null) {
            $limits->setMaxInputTokens($maxInputTokens);
        }

        if ($maxOutputTokens !== null && $limits->getMaxOutputTokens() === null) {
            $limits->setMaxOutputTokens($maxOutputTokens);
        }
    }

    /**
     * Copies pricing ranges from $pricing onto the model when the model has none yet.
     *
     * Standard and priority ranges are checked independently; if the model already has
     * standard ranges but no priority ranges, only the priority ranges are copied.
     * Throws when the model's pricing object is not a {@see ChatAiModelPricing} instance.
     */
    protected function enrichChatPricing(
        AiModel                 $model,
        ChatAiModelPricing|null $pricing
    ): void
    {
        if ($pricing === null) {
            return;
        }

        $currentPricing = $model->pricing;
        if ($currentPricing instanceof NullPricing) {
            $currentPricing = $model->pricing = ChatAiModelPricing::fromArray([]);
        } elseif (!$currentPricing instanceof ChatAiModelPricing) {
            throw InvalidModelEnrichmentTypeException::forInvalidPricingType((string) $model->model_id, get_debug_type($currentPricing));
        }

        if (!$currentPricing->hasRanges() && $pricing->hasRanges()) {
            $currentPricing->setRanges($pricing->getRanges());
        }

        if (!$currentPricing->hasRanges(true) && $pricing->hasRanges(true)) {
            $currentPricing->setRanges($pricing->getRanges(true), true);
        }
    }

    /**
     * Merges native capability keys into the model's capabilities set, deduplicating.
     *
     * "Native" capabilities are features the provider itself exposes (e.g. built-in web
     * search), as opposed to capabilities implemented on the HAWKI side via tools.
     */
    protected function enrichNativeCapabilities(AiModel $model, array|null $capabilities): void
    {
        if (empty($capabilities)) {
            return;
        }

        $existingCapabilities = $model->native_capabilities->toArray();
        $mergedCapabilities = array_merge($capabilities, $existingCapabilities);
        $model->native_capabilities = NativeAiModelCapabilities::fromArray($mergedCapabilities);
    }

    /**
     * Normalises $methods and merges them into the named $property ('input' or 'output')
     * of the model, deduplicating by the tag-list normalisation rules.
     */
    private static function enrichIoMethods(string $property, AiModel $model, string|array|AiModelIoMethods|null $methods): void
    {
        if ($methods === null) {
            return;
        }
        if (is_string($methods)) {
            $methods = [$methods];
        }
        if (is_array($methods)) {
            $methods = AiModelIoMethods::fromArray($methods);
        }
        $newMethods = $methods->toArray();
        $existingMethods = $model->{$property}?->toArray() ?? [];
        $mergedMethods = array_unique(array_merge($existingMethods, $newMethods));
        $model->{$property} = AiModelIoMethods::fromArray($mergedMethods);
    }
}
