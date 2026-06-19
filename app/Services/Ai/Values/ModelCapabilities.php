<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Casts\Contracts\CastableInstanceInterface;
use App\Services\Ai\Exceptions\InvalidModelCapabilityException;
use App\Services\Ai\Registries\AiModelCapabilityRegistry;
use Illuminate\Support\Traits\Macroable;

/**
 * Registry-validated, mutable capabilities bag for a single AI model.
 *
 * Capabilities represent high-level features — such as web search or knowledge-base
 * access — that span different AI providers. The value type ({@see ModelCapabilityValueType})
 * controls how a feature is activated: disabled entirely (`NO`), preferred via a
 * registered tool (`YES`/`TOOL`), or activated through a provider-native mechanism
 * (`NATIVE`).
 *
 * Every key stored here must first be declared in {@see AiModelCapabilityRegistry};
 * attempting to write an undeclared key throws {@see InvalidModelCapabilityException}.
 *
 * Built-in keys are defined as constants in {@see WellKnownCapabilities}.
 * Plugins may register additional keys via {@see AiModelCapabilityRegistry::declare()}
 * inside a service provider.
 *
 * This object is the `$capabilities` attribute on {@see \App\Models\Ai\AiModel},
 * automatically hydrated from and serialised to JSON via the
 * {@see \App\Casts\AsInstance} cast. On hydration, any value that no longer maps to
 * a valid {@see ModelCapabilityValueType} case is silently dropped, making the object
 * self-healing against outdated or misspelled persisted data.
 *
 * The class is {@see Macroable} so third-party packages can attach convenience
 * accessors without subclassing.
 *
 * Example:
 * ```php
 * // Read a well-known capability on a model loaded from the database
 * if ($model->capabilities->canUseWebSearch()) { ... }
 *
 * // Fluent mutation before saving
 * $model->capabilities
 *     ->setWebSearch(ModelCapabilityValueType::NATIVE)
 *     ->setKnowledgeBase(ModelCapabilityValueType::TOOL);
 * $model->save();
 *
 * // Plugin: register a custom key in a service provider, then use it
 * $registry->declare('my_plugin.custom_search', ModelCapabilityValueType::NO);
 * $model->capabilities->set('my_plugin.custom_search', ModelCapabilityValueType::YES);
 * if ($model->capabilities->canUse('my_plugin.custom_search')) { ... }
 * ```
 *
 * @api
 */
class ModelCapabilities implements \JsonSerializable, CastableInstanceInterface
{
    use Macroable;

    private function __construct(
        private array                              $capabilities,
        private readonly AiModelCapabilityRegistry $capabilityRegistry
    )
    {
    }

    // -------------------------------------------------------
    // Well known capabilities
    // -------------------------------------------------------

    /** Returns true when the web-search capability is active (any value other than NO). */
    public function canUseWebSearch(): bool
    {
        return $this->canUse(WellKnownCapabilities::WEB_SEARCH);
    }

    /** Returns the current web-search capability value, defaulting to NO when unset. */
    public function getWebSearch(): ModelCapabilityValueType
    {
        return $this->get(WellKnownCapabilities::WEB_SEARCH, ModelCapabilityValueType::NO);
    }

    /** Sets the web-search capability value. */
    public function setWebSearch(ModelCapabilityValueType $value): self
    {
        return $this->set(WellKnownCapabilities::WEB_SEARCH, $value);
    }

    /** Returns true when the knowledge-base capability is active (any value other than NO). */
    public function canUseKnowledgeBase(): bool
    {
        return $this->canUse(WellKnownCapabilities::KNOWLEDGE_BASE);
    }

    /** Returns the current knowledge-base capability value, defaulting to NO when unset. */
    public function getKnowledgeBase(): ModelCapabilityValueType
    {
        return $this->get(WellKnownCapabilities::KNOWLEDGE_BASE, ModelCapabilityValueType::NO);
    }

    /** Sets the knowledge-base capability value. */
    public function setKnowledgeBase(ModelCapabilityValueType $value): self
    {
        return $this->set(WellKnownCapabilities::KNOWLEDGE_BASE, $value);
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /**
     * Returns true when the capability identified by $key is active,
     * i.e. its resolved value is anything other than {@see ModelCapabilityValueType::NO}.
     *
     * @see WellKnownCapabilities for built-in keys; any declared string is valid for plugin keys.
     */
    public function canUse(string $key): bool
    {
        return $this->get($key, ModelCapabilityValueType::NO) !== ModelCapabilityValueType::NO;
    }

    /**
     * Resolves a capability value using the following order:
     * 1. Explicit value set on this model
     * 2. Default declared in {@see AiModelCapabilityRegistry}
     * 3. The $default argument
     *
     * @see WellKnownCapabilities for built-in keys; any declared string is valid for plugin keys.
     */
    public function get(string $key, ModelCapabilityValueType|null $default = null): null|ModelCapabilityValueType
    {
        return $this->capabilities[$key] ?? $this->capabilityRegistry->get($key) ?? $default;
    }

    /**
     * Stores a capability value for this model.
     *
     * The $key must be declared in {@see AiModelCapabilityRegistry}; an undeclared key
     * throws {@see InvalidModelCapabilityException}.
     *
     * As a storage optimisation, if the supplied value is identical to the registry
     * default for this key, any explicit entry is cleared — subsequent reads will then
     * return the registry default directly.
     *
     * @see WellKnownCapabilities for built-in keys; any declared string is valid for plugin keys.
     */
    public function set(string $key, ModelCapabilityValueType $value): self
    {
        if (!$this->capabilityRegistry->has($key)) {
            throw InvalidModelCapabilityException::forUndeclaredKey($key);
        }

        // Remove key if it is set to the current default
        if ($this->capabilityRegistry->get($key) === $value) {
            unset($this->capabilities[$key]);
            return $this;
        }

        $this->capabilities[$key] = $value;

        return $this;
    }

    /**
     * Creates an instance from a raw capabilities array (e.g. decoded from a JSON column).
     * When $capabilityRegistry is omitted it is resolved from the application container.
     *
     * Any entry whose value cannot be mapped to a {@see ModelCapabilityValueType} case is
     * silently dropped, preventing issues caused by outdated or misspelled persisted data.
     */
    public static function fromArray(array $data, ?AiModelCapabilityRegistry $capabilityRegistry = null): static
    {
        return new static(
        // Ensure that we are creating a self-fixing declaration that silently removes
        // any invalid capabilities that might be present in the input data, to prevent issues with typos or outdated capability keys.
            capabilities: array_filter(
                array_map(
                    static fn(mixed $value) => ModelCapabilityValueType::tryFrom((string)$value),
                    $data
                )
            ),
            capabilityRegistry: $capabilityRegistry ?? app(AiModelCapabilityRegistry::class)
        );
    }

    /** Returns only the capabilities that are explicitly set on this model, with enum values serialised to their string backing value. */
    public function toArray(): array
    {
        return array_map(
            static fn(ModelCapabilityValueType $value) => $value->value,
            $this->capabilities
        );
    }

    public function jsonSerialize(): array
    {
        // Everything where not no as a key array
        return array_keys(
            array_filter(
                $this->capabilities,
                static fn(ModelCapabilityValueType $value) => $value !== ModelCapabilityValueType::NO
            )
        );
    }
}
