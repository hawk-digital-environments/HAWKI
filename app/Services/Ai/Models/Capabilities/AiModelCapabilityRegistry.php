<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Capabilities;

use App\Services\Ai\Models\Capabilities\Values\AiModelCapabilityDefinition;
use App\Services\Ai\Models\Capabilities\Values\WellKnownCapabilities;
use App\Services\Ai\Utils\Traits\TranslatableRegistryTrait;
use Illuminate\Container\Attributes\Singleton;
use Traversable;

/**
 * Singleton registry for model capability declarations.
 *
 * Capabilities represent features a model can perform — such as web search, knowledge-base
 * lookup, or code execution. Each capability is declared once with a unique key and optional
 * UI metadata (title label, description label, icon path), then referenced by that key
 * wherever capability checks or UI presentation are needed.
 *
 * Declarations are made from service providers using {@see declare()}. The registry is
 * iterable, yielding all declared capabilities as {@see AiModelCapabilityDefinition} objects.
 *
 * Example (service provider):
 * ```php
 * $this->app->extend(
 *     AiModelCapabilityRegistry::class,
 *     fn(AiModelCapabilityRegistry $registry) => $registry
 *         ->declare(
 *             key: WellKnownCapabilities::WEB_SEARCH,
 *             titleTranslationLabel: 'capabilities.web_search.title',
 *             descriptionTranslationLabel: 'capabilities.web_search.description',
 *             iconPath: resource_path('icons/web-search.svg')
 *         )
 * );
 * ```
 *
 * @see WellKnownCapabilities for the built-in capability keys.
 * @see AiServiceProvider for the built-in declarations.
 * @api
 * @implements \IteratorAggregate<string, AiModelCapabilityDefinition>
 */
#[Singleton]
class AiModelCapabilityRegistry implements \IteratorAggregate
{
    use TranslatableRegistryTrait;

    private array $iconPaths = [];
    private array $keys = [];

    /**
     * Registers a capability with optional UI metadata.
     *
     * The $iconPath must be an absolute filesystem path — not a public URL.
     * The API layer converts it to a data URI before sending it to clients.
     *
     * @see WellKnownCapabilities for the built-in keys.
     */
    public function declare(
        string  $key,
        ?string $titleTranslationLabel = null,
        ?string $descriptionTranslationLabel = null,
        ?string $iconPath = null
    ): self
    {
        $this->keys[$key] = $key;
        $this->titleTranslationLabels[$key] = $titleTranslationLabel;
        $this->descriptionTranslationLabels[$key] = $descriptionTranslationLabel;
        $this->iconPaths[$key] = $iconPath;
        return $this;
    }

    /** Returns true when $key has been declared in this registry. */
    public function has(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    /** Returns the capability definition for $key, or null when not declared. */
    public function getDefinition(string $key): AiModelCapabilityDefinition|null
    {
        return $this->has($key) ? new AiModelCapabilityDefinition(
            key: $key,
            titleLabel: $this->getTitleLabel($key),
            descriptionLabel: $this->getDescriptionLabel($key),
            iconPath: $this->getIconPath($key)
        ) : null;
    }

    /**
     * Returns the filesystem path to the capability's icon, or null when none was declared.
     *
     * This is NOT a public URL — the content is provided as a data URI in the API response.
     */
    public function getIconPath(string $key): ?string
    {
        return $this->iconPaths[$key] ?? null;
    }

    /** Yields all declared capabilities as {@see AiModelCapabilityDefinition} objects, keyed by key. */
    public function getIterator(): Traversable
    {
        foreach ($this->keys as $key) {
            yield $key => $this->getDefinition($key);
        }
    }
}
