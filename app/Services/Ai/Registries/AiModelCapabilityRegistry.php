<?php
declare(strict_types=1);


namespace App\Services\Ai\Registries;

use App\Services\Ai\Registries\Traits\TranslatableRegistryTrait;
use App\Services\Ai\Tools\Values\AiToolCapabilityDefinition;
use App\Services\Ai\Values\ModelCapabilityValueType;
use App\Services\Ai\Values\WellKnownCapabilities;
use Illuminate\Container\Attributes\Singleton;
use Traversable;

/**
 *
 * @see \App\Providers\AiServiceProvider for the built-in registrations.
 * @api
 * @implements \IteratorAggregate<string, ModelCapabilityValueType>
 */
#[Singleton]
class AiModelCapabilityRegistry implements \IteratorAggregate
{
    use TranslatableRegistryTrait;

    private array $capabilities = [];
    private array $iconPaths = [];

    /**
     * Declare a capability for AI models.
     *
     * The $key is a unique identifier for the capability, e.g. "text_generation" or "image_recognition".
     * The $defaultValue is the default value for this capability, which can be of any type (string, boolean, array, etc.) depending on the capability.
     * The $titleTranslationLabel and $descriptionTranslationLabel are optional translation labels for the capability's title and description, which can be used in the UI to display user-friendly information about the capability. If not provided, the capability can still be used but may not have a user-friendly title or description in the UI.
     * The $iconPath is an optional filesystem path to an image that represents the capability in the UI. If not provided, the capability will not have a custom icon in the UI.
     * @return $this
     * @see WellKnownCapabilities for examples of well-known capability keys and their expected value types.
     */
    public function declare(
        string                   $key,
        ModelCapabilityValueType $defaultValue,
        ?string                  $titleTranslationLabel = null,
        ?string                  $descriptionTranslationLabel = null,
        ?string                  $iconPath = null
    ): self
    {
        $this->capabilities[$key] = $defaultValue;
        $this->titleTranslationLabels[$key] = $titleTranslationLabel;
        $this->descriptionTranslationLabels[$key] = $descriptionTranslationLabel;
        $this->iconPaths[$key] = $iconPath;
        return $this;
    }

    /**
     * @see WellKnownCapabilities  (Can be any string for plugins)
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->capabilities);
    }

    /**
     * @see WellKnownCapabilities (Can be any string for plugins)
     */
    public function get(string $key): ModelCapabilityValueType|null
    {
        return $this->capabilities[$key] ?? null;
    }

    /**
     * Returns the capability definition for a given key, or null if the capability is not declared.
     */
    public function getDefinition(string $key): AiToolCapabilityDefinition|null
    {
        return $this->has($key) ? new AiToolCapabilityDefinition(
            key: $key,
            defaultValue: $this->get($key),
            titleLabel: $this->getTitleLabel($key),
            descriptionLabel: $this->getDescriptionLabel($key),
            iconPath: $this->getIconPath($key)
        ) : null;
    }

    /**
     * Returns the icon path for a given capability key, or null if not set.
     * The icon path is a filesystem path to an image that represents the capability.
     * This is NOT the public URL path! The content will be provided as a data-uri in the API response.
     */
    public function getIconPath(string $key): ?string
    {
        return $this->iconPaths[$key] ?? null;
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->capabilities);
    }
}
