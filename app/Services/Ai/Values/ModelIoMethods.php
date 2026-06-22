<?php
declare(strict_types=1);


namespace App\Services\Ai\Values;


use App\Casts\Contracts\CastableInstanceInterface;
use Traversable;

/**
 * @api
 */
final class ModelIoMethods implements CastableInstanceInterface, \JsonSerializable, \IteratorAggregate
{
    private function __construct(private array $list)
    {
    }

    // -------------------------------------------------------
    // Well known methods
    // -------------------------------------------------------

    /**
     * Returns true if the list contains the 'text' method, false otherwise.
     */
    public function hasText(): bool
    {
        return $this->has('text');
    }

    /**
     * Returns true if the list contains the 'image' method, false otherwise.
     */
    public function hasImage(): bool
    {
        return $this->has('image');
    }

    /**
     * Returns true if the list contains the 'thought' method, false otherwise.
     * Note: This is mostly an "output" method.
     */
    public function hasThought(): bool
    {
        return $this->has('thought');
    }

    /**
     * Returns true if the list contains the 'video' method, false otherwise.
     */
    public function hasVideo(): bool
    {
        return $this->has('video');
    }

    /**
     * Returns true if the list contains the 'audio' method, false otherwise.
     */
    public function hasAudio(): bool
    {
        return $this->has('audio');
    }

    // -------------------------------------------------------
    // Modification and checks
    // -------------------------------------------------------

    /**
     * Returns true if the list contains the given method, false otherwise.
     */
    public function has(string $method): bool
    {
        return in_array($this->normalizeMethod($method), $this->list, true);
    }

    /**
     * Adds a method to the list if it doesn't already exist.
     */
    public function add(string $method): self
    {
        $methodClean = $this->normalizeMethod($method);
        if (!in_array($methodClean, $this->list, true)) {
            $this->list[] = $methodClean;
        }
        return $this;
    }

    /**
     * Removes a method from the list if it exists.
     */
    public function remove(string $method): self
    {
        $methodClean = $this->normalizeMethod($method);
        $this->list = array_values(array_filter($this->list, static fn($m) => $m !== $methodClean));
        return $this;
    }

    /**
     * Normalizes a method name by trimming whitespace and converting to lowercase.
     */
    private function normalizeMethod(string $method): string
    {
        return strtolower(trim($method));
    }

    public function toArray(): array
    {
        return $this->list;
    }

    public static function fromArray(array $data): static
    {
        return new self($data);
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
