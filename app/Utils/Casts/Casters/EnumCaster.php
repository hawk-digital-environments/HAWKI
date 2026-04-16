<?php
declare(strict_types=1);


namespace App\Utils\Casts\Casters;


use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;

readonly class EnumCaster implements CastsValue, BuiltInCasterInterface
{
    public function __construct(
        /**
         * @var class-string<\BackedEnum> $enumClass
         */
        private string $enumClass
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function get(object $object, string $stored): mixed
    {
        if (is_a($this->enumClass, \BackedEnum::class, true)) {
            return $this->enumClass::from($stored);
        }

        // UnitEnum: stored by case name
        return \constant($this->enumClass . '::' . $stored);
    }

    /**
     * @inheritDoc
     */
    public function set(object $object, mixed $value): string
    {
        if ($value instanceof \BackedEnum) {
            return (string)$value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        return '';
    }

    /**
     * @inheritDoc
     */
    public static function argsForAttribute(?CastType $type, string $typeString, ?string $format): array|null
    {
        return $type === null && enum_exists($typeString) ? [$typeString] : null;
    }

    /**
     * @inheritDoc
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null
    {
        if ($prop->getType()?->isBuiltin()) {
            return null;
        }

        $type = $prop->getType()?->getName();
        if ($type === null) {
            return null;
        }

        return enum_exists($type) ? [$type] : null;
    }
}
