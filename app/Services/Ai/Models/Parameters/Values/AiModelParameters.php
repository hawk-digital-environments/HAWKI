<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Parameters\Values;


use App\Casts\Contracts\CastableInstanceInterface;
use App\Utils\Arrays\RecursiveMerger;
use Illuminate\Support\Traits\Macroable;

/**
 * Holds the model-level inference parameter defaults stored in the `default_params` JSON column
 * of the {@see \App\Models\Ai\AiModel} Eloquent model.
 *
 * The class is the second tier in HAWKI's three-level parameter resolution chain:
 * provider config → **model defaults** → per-request overrides (resolved by {@see \App\Services\Ai\Agents\Values\AgentRequestContext}).
 *
 * Well-known inference parameters (`temperature`, `top_p`, `max_tokens`, `max_thinking_tokens`) have
 * dedicated typed accessors with built-in fallback defaults (0.95, 1.0, 4096, 2048 respectively).
 * Any other key stored in the JSON object is treated as a provider-specific extra parameter and is
 * returned by {@see toAdditionalArray()} for forwarding to the provider adapter.
 *
 * Example:
 * ```php
 * $params = ModelParameters::fromArray([
 *     'temperature' => 0.7,
 *     'max_tokens'  => 1024,
 *     'stream'      => true,   // provider-specific extra
 * ]);
 *
 * $params->getTemperature();       // 0.7
 * $params->getMaxTokens();         // 1024
 * $params->toAdditionalArray();    // ['stream' => true]  – well-known keys excluded
 * $params->get('stream');          // true
 * $params->set('top_p', 0.9)->getTopP(); // 0.9
 * ```
 * @api
 */
final class AiModelParameters implements CastableInstanceInterface, \JsonSerializable
{
    use Macroable;

    public function __construct(private array $list = [])
    {
    }

    // -------------------------------------------------------
    // Well known methods
    // -------------------------------------------------------

    /**
     * Returns the configured temperature, or `$default` when none is set.
     * Falls back to `0.95` when neither a stored value nor `$default` is provided.
     * Temperature controls the randomness of the model's output (typically 0.0–2.0).
     */
    public function getTemperature(float|null $default = null): float
    {
        return (float)$this->get(WellKnownModelParams::TEMPERATURE, $default ?? 0.95);
    }

    /** Sets the temperature and returns the current instance for fluent chaining. */
    public function setTemperature(float $value): self
    {
        return $this->set(WellKnownModelParams::TEMPERATURE, $value);
    }

    /**
     * Returns the configured top-p (nucleus sampling) value, or `$default` when none is set.
     * Falls back to `1.0` when neither a stored value nor `$default` is provided.
     * Top-p restricts token sampling to the smallest set of tokens whose cumulative probability
     * exceeds `top_p` (typically 0.0–1.0).
     */
    public function getTopP(float|null $default = null): float
    {
        return (float)$this->get(WellKnownModelParams::TOP_P, $default ?? 1.0);
    }

    /** Sets the top-p value and returns the current instance for fluent chaining. */
    public function setTopP(float $value): self
    {
        return $this->set(WellKnownModelParams::TOP_P, $value);
    }

    /**
     * Returns the configured maximum output token limit, or `$default` when none is set.
     * Falls back to `4096` when neither a stored value nor `$default` is provided.
     * Caps the number of tokens the model may generate in a single response.
     */
    public function getMaxTokens(int|null $default = null): int
    {
        return (int)$this->get(WellKnownModelParams::MAX_TOKENS, $default ?? 4096);
    }

    /** Sets the max-tokens limit and returns the current instance for fluent chaining. */
    public function setMaxTokens(int $value): self
    {
        return $this->set(WellKnownModelParams::MAX_TOKENS, $value);
    }

    /**
     * Returns the configured maximum thinking-token limit, or `$default` when none is set.
     * Falls back to `2048` when neither a stored value nor `$default` is provided.
     * Only relevant for models that expose a separate "thinking" budget (e.g. extended reasoning
     * models). Some providers count thinking tokens against `max_tokens`; others use a separate
     * limit — consult the model's documentation when tuning both values.
     */
    public function getMaxThinkingTokens(int|null $default = null): int
    {
        return (int)$this->get(WellKnownModelParams::MAX_THINKING_TOKENS, $default ?? 2048);
    }

    /** Sets the max-thinking-tokens limit and returns the current instance for fluent chaining. */
    public function setMaxThinkingTokens(int $value): self
    {
        return $this->set(WellKnownModelParams::MAX_THINKING_TOKENS, $value);
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /** Returns `true` if `$key` exists in the parameter list. */
    public function has(string $key): bool
    {
        return isset($this->list[$key]);
    }

    /** Returns the value stored under `$key`, or `$default` when the key is absent. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->list[$key] ?? $default;
    }

    /** Stores `$value` under `$key` and returns the current instance for fluent chaining. */
    public function set(string $key, mixed $value): self
    {
        $this->list[$key] = $value;
        return $this;
    }

    /** Removes `$key` from the parameter list and returns the current instance. No-op when the key does not exist. */
    public function remove(string $key): self
    {
        unset($this->list[$key]);
        return $this;
    }

    /**
     * Merges the current instance with another `ModelParameters` instance, returning a new instance
     * with the combined parameters. In case of overlapping keys, the values from `$other` take
     * precedence over the current instance.
     */
    public function mergeWith(self $other): self
    {
        return new self(
            RecursiveMerger::merge(
                $this->list,
                $other->list,
            )
        );
    }

    /**
     * Creates an instance from a plain array, typically decoded from the JSON stored in the
     * database. Called automatically by {@see \App\Casts\AsInstance} when the Eloquent model
     * is hydrated.
     */
    public static function fromArray(array $data): static
    {
        return new self($data);
    }

    /** Returns all stored parameters as a plain array (including well-known keys). */
    public function toArray(): array
    {
        return $this->list;
    }

    /**
     * Returns all stored parameters except the four well-known inference keys
     * (`temperature`, `top_p`, `max_tokens`, `max_thinking_tokens`).
     *
     * Used by {@see \App\Services\Ai\Agents\Values\AgentRequestContext::getAdditionalParameters()}
     * to forward provider-specific extras to the adapter without duplicating the
     * parameters that are already handled through dedicated API fields.
     */
    public function toAdditionalArray(): array
    {
        $result = $this->toArray();
        unset(
            $result[WellKnownModelParams::TEMPERATURE],
            $result[WellKnownModelParams::TOP_P],
            $result[WellKnownModelParams::MAX_TOKENS],
            $result[WellKnownModelParams::MAX_THINKING_TOKENS]
        );
        return $result;
    }

    /** Serializes all stored parameters to JSON (persisted in the database). */
    public function jsonSerialize(): array
    {
        // Ensure to contain the "default values" for the well-known parameters even when they are not explicitly set.
        return array_merge([
            WellKnownModelParams::TEMPERATURE => $this->getTemperature(),
            WellKnownModelParams::TOP_P => $this->getTopP(),
            WellKnownModelParams::MAX_TOKENS => $this->getMaxTokens(),
            WellKnownModelParams::MAX_THINKING_TOKENS => $this->getMaxThinkingTokens(),
        ], $this->toAdditionalArray());
    }
}
