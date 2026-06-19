<?php

namespace App\Services\Ai\Values;

/**
 * @deprecated Will be replaced with a "WellKnownSystemModelTypes" interface, so plugins can also define their own.
 * @todo Remove this enum and replace all usages with the new interface.
 */
enum SystemModelType: string
{
    case DEFAULT = 'default';
    case TITLE_GENERATION = 'title_generation';
    case PROMPT_IMPROVEMENT = 'prompt_improvement';
    case SUMMARY = 'summary';

    /**
     * Creates a new instance of SystemModelType from a key of the "model_providers.default_models"
     * or "model_providers.system_models" config arrays.
     *
     * @internal
     * @deprecated This is a temporary method until the config files are gone.
     */
    public static function fromLegacyKey(string $key): self
    {
        return match ($key) {
            'default_model' => self::DEFAULT,
            'title_generator' => self::TITLE_GENERATION,
            'prompt_improver' => self::PROMPT_IMPROVEMENT,
            'summarizer' => self::SUMMARY,
            // I did not bother to implement a custom exception class for this, as this is only used internally and will be removed soon anyway.
            default => throw new \InvalidArgumentException("Invalid legacy key: $key")
        };
    }
}
