<?php

declare(strict_types=1);

namespace App\Utils\Casts\Casters;

use App\Utils\Casts\AbstractCastableObject;
use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;

/**
 * Built-in caster that handles properties typed as a subclass of {@see AbstractCastableObject}.
 *
 * Activated automatically when a property's type hint is a concrete subclass of
 * {@see AbstractCastableObject} — no {@see \App\Utils\Casts\CastedValue} annotation needed.
 * It can also be referenced explicitly via its class name.
 *
 * **Storage format:** each castable object is stored as a JSON object string. When objects are
 * nested, each level is stored as a JSON-encoded string within its parent — i.e. inner JSON is
 * escaped rather than embedded. This is a deliberate trade-off: storage is slightly more verbose
 * (extra backslashes per nesting level) but the implementation stays trivial.
 *
 * Example — two levels of nesting stored in the outermost column:
 * ```json
 * {"name":"Alice","address":"{\"street\":\"Main St\",\"zip\":\"12345\"}"}
 * ```
 *
 * @see AbstractCastableObject
 * @see \App\Utils\Casts\CastedValue
 */
class CastableObjectCaster implements CastsValue, BuiltInCasterInterface
{
    public function __construct(
        /**
         * @var class-string<AbstractCastableObject>
         */
        private readonly string $castableClass
    ) {
    }

    /**
     * @inheritDoc
     */
    public function get(object $object, string $stored, string $property): mixed
    {
        $decoded = json_decode($stored, true);
        return $this->castableClass::fromStringArray(is_array($decoded) ? $decoded : []);
    }

    /**
     * @inheritDoc
     */
    public function set(object $object, mixed $value, string $property): string
    {
        if (!$value instanceof AbstractCastableObject) {
            return '{}';
        }
        return (string)(json_encode($value->toStringArray()) ?: '{}');
    }

    /**
     * @inheritDoc
     */
    public static function argsForAttribute(CastType|null $type, string $typeString, ?string $format): array|null
    {
        return $type === null
        && class_exists($typeString)
        && is_subclass_of($typeString, AbstractCastableObject::class)
            ? [$typeString]
            : null;
    }

    /**
     * @inheritDoc
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null
    {
        $type = $prop->getType();
        if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        $typeName = $type->getName();

        return class_exists($typeName) && is_subclass_of($typeName, AbstractCastableObject::class)
            ? [$typeName]
            : null;
    }
}
