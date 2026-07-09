<?php
declare(strict_types=1);


namespace App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\Applier;


use App\Models\Ai\AiModel;
use App\Services\Ai\ModelInformation\Enrichment\Implementations\LiteLlm\LiteLlmModelData;
use App\Services\Ai\ModelInformation\Enrichment\ModelInfoEnrichingTrait;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Models\Flags\Values\WellKnownModelFlags;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatAiModelPricing;
use App\Services\Ai\Models\Pricing\Values\Chat\ChatPricingRange;
use Carbon\Carbon;

/**
 * Applies LiteLLM model data to a chat-type {@see AiModel}.
 *
 * Called by {@see LiteLlmApiEnricher} once the model type has been identified as
 * {@see WellKnownModelTypes::CHAT}. Maps raw LiteLLM fields to the HAWKI model domain:
 * token limits, pricing tiers, native capabilities, model flags, documentation URL,
 * deprecation date, and I/O modalities.
 */
class ChatModelInfoApplier
{
    use ModelInfoEnrichingTrait;

    /** Applies all available LiteLLM fields to the model and returns the updated instance. */
    public function apply(AiModel $modelInfo, LiteLlmModelData $liteLlmData): AiModel
    {
        $this->enrichChatLimits(
            $modelInfo,
            $liteLlmData->max_input_tokens,
            $liteLlmData->max_output_tokens
        );

        $this->enrichChatPricing($modelInfo, $this->parsePricing($liteLlmData));
        $this->enrichNativeCapabilities($modelInfo, [...$this->collectNativeCapabilities($liteLlmData)]);
        $this->attachFlags($modelInfo, [...$this->collectFlags($liteLlmData)]);

        /** @noinspection BypassedUrlValidationInspection */
        if ($liteLlmData->source && filter_var($liteLlmData->source, FILTER_VALIDATE_URL)) {
            $modelInfo->documentation_url = $liteLlmData->source;
        }

        if (!empty($liteLlmData->deprecation_date)) {
            try {
                $modelInfo->deprecation_date = new Carbon($liteLlmData->deprecation_date);
            } catch (\Throwable) {
            }
        }

        $this->enrichInput($modelInfo, [...$this->collectModalities($liteLlmData, true)]);
        $this->enrichOutput($modelInfo, [...$this->collectModalities($liteLlmData, false)]);

        return $modelInfo;
    }

    /**
     * Yields supported modality strings ('text', 'image', 'video', 'audio'), filtered to the
     * known set. Pass $input = true for input modalities, false for output modalities.
     */
    private function collectModalities(LiteLlmModelData $liteLlmData, bool $input): iterable
    {
        $list = $input ? $liteLlmData->supported_modalities : $liteLlmData->supported_output_modalities;
        $supported = ['text', 'image', 'video', 'audio'];
        if (is_array($list)) {
            foreach ($list as $modality) {
                if (in_array($modality, $supported, true)) {
                    yield $modality;
                }
            }
        }
    }

    /**
     * Yields {@see WellKnownCapabilities} keys for each LiteLLM capability flag that is true.
     */
    private function collectNativeCapabilities(LiteLlmModelData $liteLlmData): iterable
    {
        if ($liteLlmData->supports_code_execution) {
            yield WellKnownCapabilities::CODE_EXECUTION;
        }
        if ($liteLlmData->supports_function_calling) {
            yield WellKnownCapabilities::TOOL_CALLING;
        }
        if ($liteLlmData->supports_web_search) {
            yield WellKnownCapabilities::WEB_SEARCH;
        }
    }

    /**
     * Yields {@see WellKnownModelFlags} keys derived from the LiteLLM feature flags.
     *
     * Reasoning-level flags (none/low/minimal/high/xhigh/max) are only emitted when either
     * `supports_adaptive_thinking` or `supports_reasoning` is set.
     */
    private function collectFlags(LiteLlmModelData $liteLlmData): iterable
    {
        if ($liteLlmData->supports_adaptive_thinking || $liteLlmData->supports_reasoning) {
            yield WellKnownModelFlags::FEATURE_REASONING;
            yield WellKnownModelFlags::FEATURE_REASONING_MEDIUM;
            if ($liteLlmData->supports_none_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_NONE;
            }
            if ($liteLlmData->supports_low_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_LOW;
            }
            if ($liteLlmData->supports_minimal_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_MINIMAL;
            }
            if ($liteLlmData->supports_high_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_HIGH;
            }
            if ($liteLlmData->supports_xhigh_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_X_HIGH;
            }
            if ($liteLlmData->supports_max_reasoning_effort) {
                yield WellKnownModelFlags::FEATURE_REASONING_MAX;
            }
        }

        if ($liteLlmData->supports_multimodal) {
            yield WellKnownModelFlags::MULTI_MODAL;
        }

        if ($liteLlmData->supports_native_streaming) {
            yield WellKnownModelFlags::FEATURE_STREAMING;
        }

        if ($liteLlmData->supports_sampling_params) {
            yield WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS;
        }

        if ($liteLlmData->supports_prompt_caching) {
            yield WellKnownModelFlags::FEATURE_PROMPT_CACHING;
        }

        if ($liteLlmData->supports_code_execution) {
            yield WellKnownModelFlags::STRENGTH_CODE_GENERATION;
        }

        if ($liteLlmData->supports_response_schema) {
            yield WellKnownModelFlags::FEATURE_RESPONSE_SCHEMA;
        }
    }

    /**
     * Builds a {@see ChatAiModelPricing} from the LiteLLM cost fields.
     *
     * Handles three formats: explicit tiered pricing (from the `tiered_pricing` array),
     * range-based pricing (from `input_cost_per_token_above_*k_tokens` fields), and flat
     * pricing. Character-based prices are converted to approximate token prices (×4).
     * Priority-tier pricing (models with `_priority` cost variants) is placed in a
     * separate priority range set.
     */
    private function parsePricing(LiteLlmModelData $liteLlmData): ChatAiModelPricing
    {
        $ranges = [];
        $priorityRanges = [];

        if (empty($liteLlmData->tiered_pricing)) {
            $ranges = $this->collectNonTierNonPriorityRanges($liteLlmData);
            $priorityRanges = $this->collectNonTierPriorityRanges($liteLlmData);
        } else {
            foreach ($liteLlmData->tiered_pricing as $tier) {
                $ranges[] = new ChatPricingRange(
                    currency: ChatPricingRange::CURRENCY_USD,
                    inputCostPerToken: $tier['input_cost_per_token'] ?? null,
                    inputCostPerCachedToken: $tier['cache_read_input_token_cost'] ?? null,
                    outputCostPerToken: $tier['output_cost_per_token'] ?? null,
                    outputCostPerReasoningToken: $tier['output_cost_per_reasoning_token'] ?? null,
                    rangeStart: $tier['range'][0] ?? 0,
                    rangeEnd: $tier['range'][1] ?? PHP_INT_MAX
                );
            }
        }

        return new ChatAiModelPricing(
            ranges: empty($ranges) ? null : $ranges,
            priorityRanges: empty($priorityRanges) ? null : $priorityRanges
        );
    }

    /**
     * Builds standard pricing ranges from token-range cost fields, ordered highest-tier first.
     *
     * Each `above_Nk_tokens` field creates a new range starting at N tokens; the range end
     * is set to the start of the next higher tier. A base range covering 0 to the lowest
     * tier boundary is always appended last.
     */
    private function collectNonTierNonPriorityRanges(LiteLlmModelData $liteLlmData): array
    {
        $ranges = [];

        $inputCostPerCharacter = $liteLlmData->input_cost_per_character;
        $outputCostPerCharacter = $liteLlmData->output_cost_per_character;

        $rangeEnd = PHP_INT_MAX;

        if (!empty($liteLlmData->input_cost_per_token_above_512k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_512k_tokens,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_512k_tokens,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 512000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 512000);
        }

        if (!empty($liteLlmData->input_cost_per_token_above_272k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_272k_tokens,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_272k_tokens,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 272000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 272000);
        }

        if (!empty($liteLlmData->input_cost_per_token_above_256k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_256k_tokens,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_256k_tokens,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 256000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 256000);
        }

        if (!empty($liteLlmData->input_cost_per_token_above_200k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_200k_tokens,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_200k_tokens,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 200000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 200000);
        }

        if (!empty($liteLlmData->input_cost_per_character_above_128k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_character_above_128k_tokens * 4,
                inputCostPerCachedToken: $liteLlmData->input_cost_per_character_above_128k_tokens,
                outputCostPerToken: $liteLlmData->output_cost_per_character_above_128k_tokens * 4,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 128000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 128000);
        }

        if (!empty($liteLlmData->input_cost_per_token_above_128k_tokens)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_128k_tokens,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_128k_tokens,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
                rangeStart: 128000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 128000);
        }

        $ranges[] = new ChatPricingRange(
            currency: ChatPricingRange::CURRENCY_USD,
            inputCostPerToken: $liteLlmData->input_cost_per_token ?? ($inputCostPerCharacter * 4),
            inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost,
            outputCostPerToken: $liteLlmData->output_cost_per_token ?? ($outputCostPerCharacter * 4),
            outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token,
            rangeStart: 0,
            rangeEnd: $rangeEnd
        );

        return $ranges;
    }

    /**
     * Builds priority-tier pricing ranges from the `_priority` cost fields.
     *
     * Uses the same range construction logic as {@see collectNonTierNonPriorityRanges()}
     * but reads from the `*_priority` fields. Falls back to the standard cached-token cost
     * when no priority-specific cache cost is declared.
     */
    private function collectNonTierPriorityRanges(LiteLlmModelData $liteLlmData): array
    {
        $ranges = [];

        $rangeEnd = PHP_INT_MAX;

        if (!empty($liteLlmData->input_cost_per_token_above_272k_tokens_priority)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_272k_tokens_priority,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost_priority ?? $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_272k_tokens_priority,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token ?? $liteLlmData->output_cost_per_token_above_272k_tokens_priority,
                rangeStart: 272000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 272000);
        }

        if (!empty($liteLlmData->input_cost_per_token_above_200k_tokens_priority)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_above_200k_tokens_priority,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost_priority ?? $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_above_200k_tokens_priority,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token ?? $liteLlmData->output_cost_per_token_above_200k_tokens_priority,
                rangeStart: 200000,
                rangeEnd: $rangeEnd
            );
            $rangeEnd = min($rangeEnd, 200000);
        }

        if (!empty($liteLlmData->input_cost_per_token_priority)) {
            $ranges[] = new ChatPricingRange(
                currency: ChatPricingRange::CURRENCY_USD,
                inputCostPerToken: $liteLlmData->input_cost_per_token_priority,
                inputCostPerCachedToken: $liteLlmData->cache_read_input_token_cost_priority ?? $liteLlmData->cache_read_input_token_cost,
                outputCostPerToken: $liteLlmData->output_cost_per_token_priority,
                outputCostPerReasoningToken: $liteLlmData->output_cost_per_reasoning_token ?? $liteLlmData->output_cost_per_token_priority,
                rangeStart: 0,
                rangeEnd: $rangeEnd
            );
        }

        return $ranges;
    }

}
