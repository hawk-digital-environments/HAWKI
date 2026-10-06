<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

/**
 * A redacted admin row, separate from the public API's Eloquent models.
 */
abstract readonly class Record
{
    public function __construct(public array $row)
    {
    }

    final public function id(): string
    {
        return (string) $this->row['id'];
    }
}
