<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Settings\Values;


use App\Casts\Contracts\CastableInstanceInterface;
use App\Services\Ai\Exceptions\InvalidModelSettingException;
use App\Services\Ai\Models\Settings\AiModelSettingRegistry;
use App\Services\System\Container\ServiceLocatorTrait;
use Illuminate\Support\Traits\Macroable;

/**
 * Registry-validated, mutable settings bag for a single AI model.
 *
 * Settings control runtime behaviour — whether a model may call tools, accept
 * file uploads, or be accessed from external applications. Every key stored here
 * must first be declared in {@see AiModelSettingRegistry}; attempting to write an
 * undeclared key throws {@see InvalidModelSettingException}.
 *
 * Built-in keys are defined as constants in {@see WellKnownModelSettings}.
 * Plugins may register additional keys via {@see AiModelSettingRegistry::declare()}
 * inside a service provider.
 *
 * This object is the `$settings` attribute on {@see \App\Models\Ai\AiModel},
 * automatically hydrated from and serialised to JSON via the
 * {@see \App\Casts\AsInstance} cast.
 *
 * The class is {@see Macroable} so third-party packages can attach convenience
 * accessors without subclassing.
 *
 * Example:
 * ```php
 * // Read well-known settings on a model loaded from the database
 * if ($model->settings->canUseTools()) { ... }
 *
 * // Fluent mutation before saving
 * $model->settings
 *     ->setUseTools(true)
 *     ->setHandleFiles(true)
 *     ->setMaxToolCallingRounds(5);
 * $model->save();
 *
 * // Plugin: register a custom key in a service provider, then use it on a model
 * $registry->declare('my_plugin.feature_x', false);
 * $model->settings->set('my_plugin.feature_x', true);
 * ```
 *
 * @api
 */
class AiModelSettings implements \JsonSerializable, CastableInstanceInterface
{
    use Macroable;

    use ServiceLocatorTrait;

    private function __construct(
        private array $settings
    )
    {
    }

    // -------------------------------------------------------
    // Well known settings
    // -------------------------------------------------------

    /** Returns true when this model is allowed to make tool (function) calls. */
    public function canUseTools(): bool
    {
        return $this->get(WellKnownModelSettings::TOOL_CALLING, false) === true;
    }

    /** Grants or revokes tool-calling permission for this model. */
    public function setUseTools(bool $state): self
    {
        return $this->set(WellKnownModelSettings::TOOL_CALLING, $state);
    }

    // If canUseTools, this is automatically false
    public function canUseNativeCapabilities(): bool
    {
        if (!$this->canUseTools()) {
            return false;
        }
        return $this->get(WellKnownModelSettings::NATIVE_CAPABILITIES, false) === true;
    }

    public function setUseNativeCapabilities(bool $state): self
    {
        return $this->set(WellKnownModelSettings::NATIVE_CAPABILITIES, $state);
    }

    /** Returns true when file uploads are allowed for this model. */
    public function canHandleFiles(): bool
    {
        return $this->get(WellKnownModelSettings::FILE_UPLOAD, false) === true;
    }

    /** Grants or revokes file-upload permission for this model. */
    public function setHandleFiles(bool $state): self
    {
        return $this->set(WellKnownModelSettings::FILE_UPLOAD, $state);
    }

    /**
     * Returns the maximum number of tool-calling rounds a run may use — a
     * safeguard against infinite tool-call cycles.
     *
     * When the model carries no explicit value, the registry default applies
     * (5, declared in AiServiceProvider). An explicit 0 is valid and means
     * "off": unlimited generations, bounded only by the per-tool call ceilings
     * ({@see \App\Services\Ai\Agents\Adapters\AbstractTextGeneratingAgent::maxSteps()}).
     */
    public function getMaxToolCallingRounds(): int
    {
        return (int)$this->get(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS);
    }

    /**
     * Sets the maximum consecutive tool-calling rounds.
     *
     * 0 explicitly disables the limit (unlimited); a model without an explicit
     * value falls back to the registry default.
     */
    public function setMaxToolCallingRounds(int $rounds): self
    {
        return $this->set(WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS, $rounds);
    }

    // -------------------------------------------------------
    // Generics
    // -------------------------------------------------------

    /**
     * Returns true when $key has been explicitly set on this model instance.
     * When $includeDefault is true, the check also returns true for any key
     * declared in the registry — even without an explicit value on this model.
     */
    public function has(string $key, bool $includeDefault = false): bool
    {
        return isset($this->settings[$key]) || ($includeDefault && $this->getSettingRegistry()->has($key));
    }

    /**
     * Resolves a setting value using the following order:
     * 1. Explicit value set on this model
     * 2. Default declared in {@see AiModelSettingRegistry}
     * 3. The $default argument
     *
     * @see WellKnownModelSettings for built-in keys; any string is valid for plugin keys.
     */
    public function get(string $key, string|bool|array|null|int|float $default = null): string|bool|array|null|int|float
    {
        return $this->settings[$key] ?? $this->getSettingRegistry()->get($key) ?? $default;
    }

    /**
     * Stores a setting value for this model.
     *
     * The $key must be declared in {@see AiModelSettingRegistry}; an undeclared key
     * throws {@see InvalidModelSettingException}.
     *
     * As a storage optimisation, if the supplied value is identical to the value
     * currently held in the explicit settings array, the explicit entry is cleared —
     * subsequent reads then fall back to the registry default.
     *
     * @see WellKnownModelSettings for built-in keys; any string is valid for plugin keys.
     */
    public function set(string $key, string|bool|array|null|int|float $value): self
    {
        if (!$this->getSettingRegistry()->has($key)) {
            throw InvalidModelSettingException::forUndeclaredKey($key);
        }

        // If we are setting the setting to its default value, we can just remove it from the defined settings to save space.
        if (($this->settings[$key] ?? null) === $value) {
            unset($this->settings[$key]);
            return $this;
        }

        $this->settings[$key] = $value;

        return $this;
    }

    /**
     * Creates an instance from a raw settings array (e.g. decoded from a JSON column).
     */
    public static function fromArray(array $data): static
    {
        /** @phpstan-ignore new.static */
        return new static($data);
    }

    /** Returns the settings that are explicitly set on this model, keyed by setting name. */
    public function toArray(): array
    {
        return $this->settings;
    }

    public function jsonSerialize(): array
    {
        return array_merge(
            $this->toArray(),
            [
                // For convenience, we also include the well-known settings as top-level keys in the JSON representation.
                // This allows us to easily access them when we only have the settings JSON (e.g. in the tool-calling loop) without needing to resolve registry defaults manually.
                WellKnownModelSettings::TOOL_CALLING => $this->canUseTools(),
                WellKnownModelSettings::FILE_UPLOAD => $this->canHandleFiles(),
                WellKnownModelSettings::MAX_TOOL_CALLING_ROUNDS => $this->getMaxToolCallingRounds(),
            ]
        );
    }

    protected function getSettingRegistry(): AiModelSettingRegistry
    {
        return $this->getService(AiModelSettingRegistry::class);
    }

}
