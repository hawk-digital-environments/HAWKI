<?php
declare(strict_types=1);


namespace App\Services\Ai\Utils\Traits;


/**
 * Adds translatable title and description label lookup to a key-based registry.
 *
 * Used by singleton registries (e.g. {@see \App\Services\Ai\Models\Settings\AiModelSettingRegistry},
 * {@see \App\Services\Ai\Models\Flags\AiModelFlagRegistry},
 * {@see \App\Services\Ai\Models\Capabilities\AiModelCapabilityRegistry}) to store
 * optional i18n translation keys alongside each declared registry entry.
 *
 * The using class must implement {@see has()} to verify that a key exists before
 * label retrieval is attempted. Both label arrays are keyed by the registry key and
 * populated by the registry's own `declare()` method; this trait only provides the
 * storage and the guarded accessors.
 */
trait TranslatableRegistryTrait
{
    private array $titleTranslationLabels = [];
    private array $descriptionTranslationLabels = [];

    abstract public function has(string $key): bool;

    /**
     * Returns the translation label for the entry's title, or null when none was declared.
     *
     * @throws \InvalidArgumentException when $key has not been declared in the registry at all.
     */
    public function getTitleLabel(string $key): string|null
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("The key '$key' is not declared in " . static::class . " and therefore does not have a title.");
        }
        return $this->titleTranslationLabels[$key] ?? null;
    }

    /**
     * Returns the translation label for the entry's description, or null when none was declared.
     *
     * @throws \InvalidArgumentException when $key has not been declared in the registry at all.
     */
    public function getDescriptionLabel(string $key): string|null
    {
        if (!$this->has($key)) {
            throw new \InvalidArgumentException("The key '$key' is not declared in " . static::class . " and therefore does not have a title.");
        }
        return $this->descriptionTranslationLabels[$key] ?? null;
    }
}
