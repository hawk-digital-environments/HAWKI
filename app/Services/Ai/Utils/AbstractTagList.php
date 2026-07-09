<?php
declare(strict_types=1);


namespace App\Services\Ai\Utils;


use App\Casts\Contracts\CastableInstanceInterface;
use Illuminate\Support\Traits\Macroable;
use Traversable;

/**
 * Ordered, deduplicated list of string tags with case-insensitive comparison.
 *
 * Concrete subclasses represent specific tag domains (e.g. {@see AiModelIoMethods} for
 * input/output modalities, {@see AiModelFlags} for model characteristic labels).
 * All values are normalised to lowercase and trimmed on insertion, so duplicates that
 * differ only in case or surrounding whitespace are silently merged.
 *
 * Implements {@see CastableInstanceInterface} so instances can be stored and retrieved
 * from Eloquent JSON columns via a matching cast. Use {@see fromArray()} to create an
 * instance from a decoded JSON array and {@see toArray()} / {@see jsonSerialize()} to
 * convert back.
 *
 * The {@see Macroable} trait allows consuming packages to extend the list type with
 * domain-specific helper methods without subclassing.
 *
 * @api
 */
abstract class AbstractTagList implements CastableInstanceInterface, \JsonSerializable, \IteratorAggregate
{
    use Macroable;

    protected array $list;

    protected function __construct(array $list)
    {
        /** @noinspection ArrayMapMissUseInspection -> We only know what is "unique" after normalizing the entries, so shush! */
        $this->list = array_values(array_unique(array_map([$this, 'normalizeValue'], $list)));
    }

    // -------------------------------------------------------
    // Modification and checks
    // -------------------------------------------------------

    /**
     * Returns true if the list contains the given tag, false otherwise.
     */
    public function has(string $tag): bool
    {
        return in_array($this->normalizeValue($tag), $this->list, true);
    }

    /**
     * Adds a tag to the list if it doesn't already exist.
     */
    public function add(string $tag): self
    {
        $tagClean = $this->normalizeValue($tag);
        if (!in_array($tagClean, $this->list, true)) {
            $this->list[] = $tagClean;
        }
        return $this;
    }

    /**
     * Removes a tag from the list if it exists.
     */
    public function remove(string $tag): self
    {
        $tagClean = $this->normalizeValue($tag);
        $this->list = array_values(array_filter($this->list, static fn($m) => $m !== $tagClean));
        return $this;
    }

    /**
     * Normalizes a tag name by trimming whitespace and converting to lowercase.
     */
    private function normalizeValue(string $tag): string
    {
        return strtolower(trim($tag));
    }

    public function toArray(): array
    {
        return $this->list;
    }

    public static function fromArray(array $data): static
    {
        // @phpstan-ignore-next-line -> We don't know the concrete class here, but we know it will have a constructor that accepts an array.
        return new static($data);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * @inheritDoc
     */
    public function getIterator(): Traversable
    {
        return new \ArrayIterator($this->list);
    }
}
