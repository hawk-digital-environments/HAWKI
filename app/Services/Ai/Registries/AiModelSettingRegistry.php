<?php
declare(strict_types=1);


namespace App\Services\Ai\Registries;

use App\Services\Ai\Registries\Traits\TranslatableRegistryTrait;
use App\Services\Ai\Values\WellKnownModelSettings;
use Illuminate\Container\Attributes\Singleton;

/**
 *
 * @see \App\Providers\AiServiceProvider for the built-in registrations.
 * @api
 */
#[Singleton]
class AiModelSettingRegistry
{
    use TranslatableRegistryTrait;

    private array $settings = [];

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

    /**
     * @see WellKnownModelSettings (Can be any string for plugins)
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->settings);
    }

    /**
     * @see WellKnownModelSettings (Can be any string for plugins)
     */
    public function get(string $key): string|bool|array|null|int|float
    {
        return $this->settings[$key] ?? null;
    }
}
