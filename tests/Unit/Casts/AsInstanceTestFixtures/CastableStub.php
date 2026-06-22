<?php
declare(strict_types=1);

namespace Tests\Unit\Casts\AsInstanceTestFixtures;

use App\Casts\Contracts\CastableInstanceInterface;

class CastableStub implements CastableInstanceInterface
{
    public function __construct(
        public readonly string $name,
        public readonly int $value,
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new static($data['name'] ?? '', $data['value'] ?? 0);
    }

    public function toArray(): array
    {
        return ['name' => $this->name, 'value' => $this->value];
    }
}
