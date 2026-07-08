<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Settings;

use App\Services\Ai\Models\Settings\Values\WellKnownModelSettings;
use App\Services\Ai\Utils\Traits\TranslatableRegistryTrait;
use Illuminate\Container\Attributes\Singleton;

/**
 * Singleton registry for AI model setting declarations.
 *
 * A setting is a per-model runtime behaviour toggle (e.g. whether tool calling is
 * allowed, or how many tool-call rounds are permitted). Every setting key must be
 * declared here with a default value before it can be written to a model. Built-in
 * keys are defined in {@see WellKnownModelSettings}.
 *
 * Declarations are made from service providers. The registry is read by
 * {@see AiModelSettings} at runtime to resolve default values for keys that have no
 * explicit per-model override.
 *
 * Example (service provider):
 * ```php
 * $this->app->extend(
 *     AiModelSettingRegistry::class,
 *     fn(AiModelSettingRegistry $registry) => $registry
 *         ->declare('my_plugin.feature_x', false, 'plugin.feature_x.title')
 * );
 * ```
 *
 * @see WellKnownModelSettings for the built-in keys.
 * @see AiServiceProvider for the built-in declarations.
 * @api
 */
#[Singleton]
class AiModelSettingRegistry
{
    use TranslatableRegistryTrait;

    private array $settings = [];

    /**
     * Registers a setting key with its default value and optional UI labels.
     *
     * The $defaultValue is returned by {@see AiModelSettings::get()} when no explicit
     * per-model value has been set.
     */
    public function declare(
        string                           $key,
        string|bool|array|null|int|float $defaultValue,
        ?string                          $titleTranslationLabel = null,
        ?string                          $descriptionTranslationLabel = null
    ): self
    {
        $this->settings[$key] = $defaultValue;
        $this->titleTranslationLabels[$key] = $titleTranslationLabel;
        $this->descriptionTranslationLabels[$key] = $descriptionTranslationLabel;
        return $this;
    }

    /** Returns true when $key has been declared in this registry. */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->settings);
    }

    /** Returns the registered default value for $key, or null when $key has not been declared. */
    public function get(string $key): string|bool|array|null|int|float
    {
        return $this->settings[$key] ?? null;
    }
}
