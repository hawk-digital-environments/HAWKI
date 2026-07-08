<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Models\Limits\Values\ChatAiModelLimits;
use App\Services\Ai\Models\ModelTypes\Values\WellKnownModelTypes;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;

class ModelInfoApplier
{
    private const array PLAIN_FIELDS = [
        'documentation_url',
        'label',
        'deprecation_date',
        'model_type'
    ];

    use ModelInfoEnrichingTrait;

    public function applyAllInformationToModel(AiModel $target, AiModel $information): void
    {
        foreach (self::PLAIN_FIELDS as $field) {
            if (empty($target->{$field}) && !empty($information->{$field})) {
                $target->{$field} = $information->{$field};
            }
        }

        $this->enrichInput($target, $information->input);
        $this->enrichOutput($target, $information->output);
        $this->attachAllDescriptions($target, $information->description);
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
