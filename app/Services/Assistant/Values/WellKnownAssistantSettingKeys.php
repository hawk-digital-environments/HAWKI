<?php

declare(strict_types=1);

namespace App\Services\Assistant\Values;

/**
 * Keys of the assistant_settings catalog that runtime code needs to know
 * beyond the generic prompt_template pipeline. Mirrors the interface style of
 * the AI-side well-known registries (e.g. WellKnownModelParams) so catalog
 * keys are referenced as symbols, not magic strings.
 */
interface WellKnownAssistantSettingKeys
{
    /**
     * The verbosity target (concise / balanced / detailed) of an assistant.
     * Its prompt fragment travels through the generic settings pipeline
     * (ANSWER_STYLE template), but AssistantPromptComposer also reads the
     * value to compose the ANSWER_LENGTH module around it.
     */
    public const string ANSWER_STYLE = 'answer_style';
}
