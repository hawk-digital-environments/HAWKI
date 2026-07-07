<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Flags\Values;


use App\Services\Ai\Utils\AbstractTagList;

/**
 * Tag list of flags attached to an AI model.
 *
 * Flags are short categorical labels that describe a model's characteristics (e.g.
 * open-weights status, supported features, domain strengths). Each flag is a string key
 * declared in {@see AiModelFlagRegistry}; built-in keys are defined in
 * {@see WellKnownModelFlags}. Stored as the `flags` JSON column on
 * {@see \App\Models\Ai\AiModel}.
 */
class AiModelFlags extends AbstractTagList
{
    // -------------------------------------------------------
    // Well known methods
    // -------------------------------------------------------

    public function hasOpenWeights(): bool
    {
        return $this->has(WellKnownModelFlags::OPEN_WEIGHTS);
    }

    public function hasEcoFriendly(): bool
    {
        return $this->has(WellKnownModelFlags::ECO_FRIENDLY);
    }

    public function isSelfHosted(): bool
    {
        return $this->has(WellKnownModelFlags::SELF_HOSTED);
    }

    public function isMultiModal(): bool
    {
        return $this->has(WellKnownModelFlags::MULTI_MODAL);
    }

    public function hasStrengthCreativeWriting(): bool
    {
        return $this->has(WellKnownModelFlags::STRENGTH_CREATIVE_WRITING);
    }

    public function hasStrengthCodeGeneration(): bool
    {
        return $this->has(WellKnownModelFlags::STRENGTH_CODE_GENERATION);
    }

    public function hasStrengthMath(): bool
    {
        return $this->has(WellKnownModelFlags::STRENGTH_MATH);
    }

    public function hasStrengthRoleplaying(): bool
    {
        return $this->has(WellKnownModelFlags::STRENGTH_ROLE_PLAYING);
    }

    public function hasStrengthReasoning(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING);
    }

    public function hasFeatureStreaming(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_STREAMING);
    }

    public function hasFeatureSamplingParameters(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_SAMPLING_PARAMETERS);
    }

    public function hasFeatureResponseSchema(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_RESPONSE_SCHEMA);
    }

    public function hasFeaturePromptCaching(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_PROMPT_CACHING);
    }

    public function hasFeatureReasoningNone(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_NONE);
    }

    public function hasFeatureReasoningMinimal(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_MINIMAL);
    }

    public function hasFeatureReasoningLow(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_LOW);
    }

    public function hasFeatureReasoningMedium(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_MEDIUM);
    }

    public function hasFeatureReasoningHigh(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_HIGH);
    }

    public function hasFeatureReasoningXHigh(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_X_HIGH);
    }

    public function hasFeatureReasoningMax(): bool
    {
        return $this->has(WellKnownModelFlags::FEATURE_REASONING_MAX);
    }
}
