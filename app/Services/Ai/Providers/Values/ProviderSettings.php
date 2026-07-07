<?php
declare(strict_types=1);


namespace App\Services\Ai\Providers\Values;


use App\Casts\Contracts\CastableInstanceInterface;
use App\Services\Ai\Exceptions\InvalidProviderSettingsOperationException;
use App\Services\Ai\Models\Parameters\Values\AiModelParameters;
use Illuminate\Support\Traits\Macroable;

/**
 * Holds provider-level settings stored in the `settings` JSON column of the
 * {@see \App\Models\Ai\AiProvider} Eloquent model.
 *
 * Two categories of settings are distinguished:
 *
 * - **Instance keys** – sub-objects that are always hydrated as typed value objects
 *   (e.g. {@see AiModelParameters} under `model_parameters`). They are guaranteed to be
 *   present after construction and cannot be removed via {@see remove()}.
 * - **Scalar / array keys** – arbitrary key-value pairs stored alongside the instance
 *   keys (e.g. `adapter` settings forwarded verbatim to the provider adapter).
 *
 * The class integrates into HAWKI's three-level parameter resolution chain where
 * provider-level defaults are the first (lowest-priority) tier. The
 * {@see \App\Services\Ai\Agents\Values\AgentRequestContext} merges them with model-level and
 * per-request overrides.
 *
 * Extension via {@see \Illuminate\Support\Traits\Macroable} is supported; macros may
 * push additional key names into `$instanceKeys` to protect them from removal.
 *
 * Example:
 * ```php
 * $settings = AiProviderSettings::fromArray([
 *     'model_parameters' => ['temperature' => 0.8, 'max_tokens' => 2048],
 *     'adapter'          => ['region' => 'eu-west-1'],
 * ]);
 *
 * $settings->getModelParameters()->getTemperature(); // 0.8
 * $settings->getAdapterSettings();                   // ['region' => 'eu-west-1']
 * $settings->get('adapter');                         // same as above via generic accessor
 * $settings->set('custom_key', 'value');             // add arbitrary scalar setting
 * $settings->remove('custom_key');                   // remove non-instance key
 * ```
 *
 * @api
 */
final class ProviderSettings implements CastableInstanceInterface, \JsonSerializable
{
    use Macroable;

    /**
     * A list of keys that are always present as typed value objects, along with the class they must be an instance of.
     * @var array<string, class-string<CastableInstanceInterface>>
     */
    private array $instanceKeys = [
        WellKnownProviderSettings::MODEL_PARAMETERS => AiModelParameters::class
    ];

    private array $list;

    /**
     * Each registered instance key is hydrated into its typed value object via `fromArray()`.
     * If the stored value for an instance key is not an array (corrupted data), it is silently
     * wrapped in `['_brokenData' => $value]` to prevent data loss and avoid hard failures.
     * Missing instance keys are initialised from an empty array.
     * @param array $list
     */
    private function __construct(array $list)
    {
        // Load all macro-defined instance keys to ensure they are protected from removal and properly initialised
        foreach (self::$macros as $macroName => $macro) {
            if (str_starts_with($macroName, 'init_macro_')) {
                $this->instanceKeys = $this->$macroName($this->instanceKeys);
            }
        }

        foreach ($this->instanceKeys as $key => $class) {
            if (isset($list[$key])) {
                if (!is_array($list[$key])) {
                    // Invalid format, silently fix by treating it as empty array to avoid errors and data loss
                    $list[$key] = ['_brokenData' => $list[$key]];
                }
                $list[$key] = $this->instanceKeys[$key]::fromArray($list[$key]);
            } else {
                $list[$key] = $this->instanceKeys[$key]::fromArray([]);
            }
        }

        $this->list = $list;
    }

    // -------------------------------------------------------
    // Well known methods
    // -------------------------------------------------------

    /**
     * Returns the provider-level model parameter defaults.
     * Always present — guaranteed to be a valid {@see AiModelParameters} instance even when
     * no `model_parameters` entry exists in the stored JSON.
     */
    public function getModelParameters(): AiModelParameters
    {
        return $this->get(WellKnownProviderSettings::MODEL_PARAMETERS);
    }

    /**
     * Returns the adapter-specific configuration array (key `adapter`).
     * These values are forwarded to the {@see \App\Services\Ai\Providers\Adapters\Contracts\ProviderAdapterInterface}
     * implementation to configure provider-specific behaviour such as API version, region, or
     * inference provider — they are never sent to the model directly.
     * Returns an empty array when no adapter settings are stored.
     */
    public function getAdapterSettings(): array
    {
        return $this->get(WellKnownProviderSettings::ADAPTER, []);
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /** Returns `true` if `$key` exists in the settings list. */
    public function has(string $key): bool
    {
        return isset($this->list[$key]);
    }

    /** Returns the value stored under `$key`, or `$default` when the key is absent. */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->list[$key] ?? $default;
    }

    /**
     * Stores `$value` under `$key` and returns the current instance for fluent chaining.
     * When `$key` is a registered instance key, `$value` must be an instance of the
     * corresponding class; otherwise {@see InvalidProviderSettingsOperationException} is thrown.
     */
    public function set(string $key, mixed $value): self
    {
        if (isset($this->instanceKeys[$key]) && !($value instanceof $this->instanceKeys[$key])) {
            throw InvalidProviderSettingsOperationException::forInvalidInstanceValue($key, $this->instanceKeys[$key]);
        }
        $this->list[$key] = $value;
        return $this;
    }

    /**
     * Removes `$key` from the settings list and returns the current instance.
     * Throws {@see InvalidProviderSettingsOperationException} when `$key` is a registered
     * instance key, because instance keys are always required to be present.
     */
    public function remove(string $key): self
    {
        if (array_key_exists($key, $this->instanceKeys)) {
            throw InvalidProviderSettingsOperationException::forRequiredInstanceKey($key);
        }

        unset($this->list[$key]);
        return $this;
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

    /**
     * Returns all settings as a plain array suitable for JSON serialization.
     * Instance key objects are recursively converted via their own `toArray()`; entries that
     * serialize to an empty array are omitted to keep the stored JSON compact.
     */
    public function toArray(): array
    {
        $data = $this->list;
        foreach (array_keys($this->instanceKeys) as $key) {
            if (isset($data[$key]) && $data[$key] instanceof CastableInstanceInterface) {
                $data[$key] = $data[$key]->toArray();
                if (empty($data[$key])) {
                    unset($data[$key]);
                }
            }
        }
        return $data;
    }

    /** Serializes to a plain array, identical to {@see toArray()}. */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
