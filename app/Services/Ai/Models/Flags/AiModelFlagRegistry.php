<?php
declare(strict_types=1);


namespace App\Services\Ai\Models\Flags;


use App\Services\Ai\Models\Flags\Values\ModelFlagDefinition;
use App\Services\Ai\Utils\Traits\TranslatableRegistryTrait;
use Illuminate\Container\Attributes\Singleton;

/**
 * Singleton registry for model flag declarations.
 *
 * Flags are short, categorical labels attached to models (e.g. "open-weights",
 * "multi-modal") displayed in the UI to help users understand a model's characteristics.
 * Each flag is declared once with a unique key and optional UI metadata (title, description,
 * color token). Built-in flag keys and their meanings are defined in {@see WellKnownModelFlags}.
 *
 * Color-code constants (COLOR_*) are UI theme tokens, not raw CSS values.
 * However, it is absolutely possible to use a CSS hex code (e.g. `#ff0000`) for a custom color if desired.
 *
 * Declarations are made from service providers using {@see declare()}.
 *
 * Example (service provider):
 * ```php
 * $this->app->extend(
 *     AiModelFlagRegistry::class,
 *     fn(AiModelFlagRegistry $registry) => $registry
 *         ->declare(
 *             key: WellKnownModelFlags::OPEN_WEIGHTS,
 *             titleTranslationLabel: 'ai.model.detail.flag.openWeights',
 *             colorCode: AiModelFlagRegistry::COLOR_HIGHLIGHT
 *         )
 * );
 * ```
 *
 * @see WellKnownModelFlags for the built-in flag keys.
 * @see AiServiceProvider for the built-in declarations.
 * @api
 * @implements \IteratorAggregate<string, ModelFlagDefinition>
 */
#[Singleton]
class AiModelFlagRegistry implements \IteratorAggregate
{
    public const string COLOR_DEFAULT = '@default';
    public const string COLOR_ERROR = '@error';
    public const string COLOR_WARNING = '@warning';
    public const string COLOR_HIGHLIGHT = '@highlight';
    public const string COLOR_SUCCESS = '@success';

    use TranslatableRegistryTrait;

    private array $colors = [];
    private array $keys = [];

    /**
     * Registers a flag key with optional display metadata.
     *
     * The $colorCode should be one of the COLOR_* class constants or a CSS hex code; defaults to
     * {@see COLOR_DEFAULT} when omitted.
     */
    public function declare(
        string  $key,
        ?string $titleTranslationLabel = null,
        ?string $descriptionTranslationLabel = null,
        ?string $colorCode = null
    ): self
    {
        $this->keys[$key] = $key;
        $this->titleTranslationLabels[$key] = $titleTranslationLabel;
        $this->descriptionTranslationLabels[$key] = $descriptionTranslationLabel;
        $this->colors[$key] = $colorCode ?? self::COLOR_DEFAULT;
        return $this;
    }

    /** Returns true when $key has been declared in this registry. */
    public function has(string $key): bool
    {
        return isset($this->keys[$key]);
    }

    /** Returns the UI color token for $key, or null when the key has not been declared. */
    public function getColor(string $key): string|null
    {
        return $this->colors[$key] ?? null;
    }

    /** Returns the flag's definition object, or null when not declared. */
    public function getDefinition(string $key): ModelFlagDefinition|null
    {
        if (!$this->has($key)) {
            return null;
        }
        return new ModelFlagDefinition(
            key: $key,
            titleLabel: $this->getTitleLabel($key),
            descriptionLabel: $this->getDescriptionLabel($key),
            colorCode: $this->getColor($key)
        );
    }

    /** Yields all declared {@see ModelFlagDefinition} objects, keyed by flag key. */
    public function getIterator(): \Traversable
    {
        foreach ($this->keys as $key) {
            yield $key => $this->getDefinition($key);
        }
    }
}
