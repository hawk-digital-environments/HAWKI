# Value Objects

Value objects live in `{Domain}/Values/`. They carry typed data with no external service dependencies — pure data, no behaviour that touches the outside world.

## When to use a value object

Use a value object when you have a typed concept that is more than a primitive: a `Locale`, a `StoredFileIdentifier`, an `AgentRequestContext`, an `AiModelParameters` bag. If you are tempted to pass an associative array around, that is a value object waiting to be extracted.

## How to build one

Value objects in HAWKI are always `readonly`. Construction goes through static factory methods (`fromXxx`, `tryFromXxx`), never through `new`.

```php
namespace App\Services\System\Values;

use App\Services\System\Exceptions\InvalidLocaleStringException;

readonly class Locale
{
    private function __construct(
        public string $code,
    ) {}

    public static function fromString(string $code): self
    {
        if (!preg_match('/^[a-z]{2}(-[A-Z]{2})?$/', $code)) {
            throw InvalidLocaleStringException::forString($code);
        }
        return new self($code);
    }
}
```

The private constructor plus named factory methods let you validate, normalise, and throw speaking exceptions at the boundary — callers cannot construct an invalid value.

## Rules

- Always `readonly`. Mutability defeats the point.
- No external service dependencies. If a value object needs the database, the clock, or the container, it is not a value object — it is a service or a repository query result.
- One concept per class. A `Locale` is a locale; it is not also a translation loader.
- Throw a [domain exception](./180-Exceptions.md) from the factory when input is invalid.

## Castable value objects on models

When a value object should hydrate from and persist to a model column, see [Model Casts](./160-Model-Casts/index.md) — HAWKI has a cast set (`AsInstance`, `AsLocale`, the crypto casts) that handles the round-trip for you.
