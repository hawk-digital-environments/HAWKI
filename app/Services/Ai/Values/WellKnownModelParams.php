<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


interface WellKnownModelParams
{
    /**
     * Temperature controls the randomness of the model's output (typically 0.0–2.0).
     */
    public const string TEMPERATURE = 'temperature';

    /**
     * Top-p (nucleus sampling) controls the diversity of the model's output by limiting the token selection
     * to a subset of tokens with a cumulative probability above a certain threshold (typically 0.0–1.0).
     */
    public const string TOP_P = 'top_p';

    /**
     * Max tokens caps the number of tokens the model may generate in a single response.
     */
    public const string MAX_TOKENS = 'max_tokens';

    /**
     * Only relevant for models that expose a separate "thinking" budget (e.g. extended reasoning
     *  models). Some providers count thinking tokens against `max_tokens`; others use a separate
     *  limit — consult the model's documentation when tuning both values.
     */
    public const string MAX_THINKING_TOKENS = 'max_thinking_tokens';

}
