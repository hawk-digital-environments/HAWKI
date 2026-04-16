<?php
declare(strict_types=1);


namespace App\Utils\Casts\Values;

/**
 * Represents a resolved caster with its class and arguments.
 * @internal
 */
readonly class ResolvedCaster implements \Stringable
{
    public function __construct(
        public string $casterClass,
        public array  $args
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function __toString(): string
    {
        return $this->casterClass . ' (' . md5(serialize($this->args)) . ')';
    }
}
