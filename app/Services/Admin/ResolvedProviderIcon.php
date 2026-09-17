<?php

declare(strict_types=1);

namespace App\Services\Admin;

/**
 * Server-validated icon content, ready to persist without remote requests.
 */
final readonly class ResolvedProviderIcon implements \JsonSerializable
{
    public function __construct(public ?array $value)
    {
    }

    public function jsonSerialize(): ?array
    {
        return $this->value;
    }
}
