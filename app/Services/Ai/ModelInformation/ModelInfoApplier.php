<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;

/**
 * Applies enrichment data from one {@see AiModel} instance onto another.
 *
 * Used in the config-file sync pipeline: after enrichment data has been fetched
 * from external sources (e.g. the LiteLLM catalog) and stored in a transient
 * {@see AiModel} object, this class transfers that data onto the persistent model
 * record that will be saved to the database.
 *
 * Transfer is always additive — fields that are already populated on $target are
 * left unchanged. This preserves operator-configured overrides (set via the config
 * file) against automatic enrichment data.
 *
 * Usage:
 * ```php
 * $applier = new ModelInfoApplier();
 * $applier->applyAllInformationToModel($persistentModel, $enrichedModel);
 * // $persistentModel now has all enrichment data merged in
 * ```
 */
class ModelInfoApplier
{
    private const array PLAIN_FIELDS = [
        'documentation_url',
        'label',
        'deprecation_date',
        'model_type'
    ];

    use ModelInfoEnrichingTrait;

    /**
     * Merges all enrichment fields from $information into $target.
     *
     * Scalar fields listed in {@see PLAIN_FIELDS} are copied only when $target has no
     * value. Structured fields (I/O methods, descriptions, flags, parameters,
     * native capabilities, limits, pricing) are merged via the trait helpers, each of
     * which follows the same fill-in-the-blank strategy.
     *
     * Limits and pricing are only transferred for chat-type models and only when the
     * $information object carries the correct typed value for that model type.
     */
    public function applyAllInformationToModel(AiModel $target, AiModel $information): void
    {
        foreach (self::PLAIN_FIELDS as $field) {
            if (empty($target->{$field}) && !empty($information->{$field})) {
                $target->{$field} = $information->{$field};
            }
        }

        $this->enrichInput($target, $information->input);
        $this->enrichOutput($target, $information->output);
        $this->attachAllDescriptions($target, $information->description->all());
        $this->attachFlags($target, $information->flags->toArray());
        $this->enrichParameters($target, $information->parameters->toArray());
        $this->enrichNativeCapabilities($target, $information->native_capabilities->toArray());

        if ($target->model_type === WellKnownModelTypes::CHAT) {
            if ($information->limits instanceof ChatAiModelLimits) {
                $this->enrichChatLimits($target, $information->limits);
            }
            if ($information->pricing instanceof ChatAiModelPricing) {
                $this->enrichChatPricing($target, $information->pricing);
            }
        }
    }
}
