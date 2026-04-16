<?php
declare(strict_types=1);


namespace App\Utils\Casts\Casters;


use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Values\CastType;

readonly class DefaultCaster implements CastsValue, BuiltInCasterInterface
{
    public function __construct(private CastType $castedType)
    {

    }

    /**
     * @inheritDoc
     */
    public function get(object $object, string $stored): mixed
    {
        return match ($this->castedType) {
            CastType::INT => (int)$stored,
            CastType::FLOAT => (float)$stored,
            CastType::BOOL => '1' === $stored || 'true' === $stored,
            CastType::STRING => $stored,
            CastType::ARRAY, CastType::JSON => json_decode($stored, true) ?? [],
            CastType::OBJECT => json_decode($stored, false) ?? new \stdClass(),
            default => $stored,
        };
    }

    /**
     * @inheritDoc
     */
    public function set(object $object, mixed $value): string
    {
        return match ($this->castedType) {
            CastType::BOOL => $value ? '1' : '0',
            CastType::ARRAY, CastType::JSON, CastType::OBJECT => json_encode($value),
            default => (string)$value,
        };
    }

    /**
     * @inheritDoc
     */
    public static function argsForAttribute(CastType|null $type, string $typeString, ?string $format): array|null
    {
        $supported = [
            CastType::INT,
            CastType::FLOAT,
            CastType::BOOL,
            CastType::STRING,
            CastType::ARRAY,
            CastType::JSON,
            CastType::OBJECT
        ];

        return in_array($type, $supported, true) ? [$type] : null;
    }

    /**
     * @inheritDoc
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null
    {
        $type = match ($prop->getType()?->getName()) {
            'int' => CastType::INT,
            'float' => CastType::FLOAT,
            'bool' => CastType::BOOL,
            'string' => CastType::STRING,
            'array' => CastType::ARRAY,
            'object' => CastType::OBJECT,
            default => null, // mixed, never, void — raw string passthrough
        };

        if (!$type) {
            return null;
        }

        return [$type];
    }
}
