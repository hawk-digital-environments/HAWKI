<?php
declare(strict_types=1);


namespace App\Services\Ai\Utils;


use App\Casts\Contracts\CastableInstanceInterface;
use Illuminate\Support\Traits\Macroable;
use Traversable;

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
