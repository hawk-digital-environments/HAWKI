<?php
declare(strict_types=1);


namespace App\Utils\Casts\Casters;


use App\Utils\Casts\Contracts\BuiltInCasterInterface;
use App\Utils\Casts\Contracts\CastsValue;
use App\Utils\Casts\Exceptions\InvalidCastTypeException;
use App\Utils\Casts\Values\CastType;
use Illuminate\Support\Facades\Crypt;

readonly class EncryptedCaster implements CastsValue, BuiltInCasterInterface
{
    private const ENCRYPTED_PREFIX = 'encrypted';
    private const ENCRYPTABLE_TYPES = [
        CastType::OBJECT,
        CastType::ARRAY,
        CastType::JSON,
        CastType::STRING,
    ];

    public function __construct(private CastType $innerType)
    {
    }

    /**
     * @inheritDoc
     */
    public function get(object $object, string $stored, string $property): mixed
    {
        $decrypted = Crypt::decryptString($stored);

        return match ($this->innerType) {
            CastType::ARRAY, CastType::JSON => json_decode($decrypted, true),
            CastType::OBJECT => json_decode($decrypted, false),
            default => $decrypted,
        };
    }

    /**
     * @inheritDoc
     */
    public function set(object $object, mixed $value, string $property): string
    {
        $plain = match ($this->innerType) {
            CastType::ARRAY, CastType::JSON, CastType::OBJECT => json_encode($value),
            default => (string)$value,
        };

        return Crypt::encryptString($plain);
    }

    /**
     * @inheritDoc
     */
    public static function argsForAttribute(CastType|null $type, string $typeString, ?string $format): array|null
    {
        // If the type is given as CastType + "encrypted" format, e.g. "string" with format "encrypted",
        // then we can directly use the type as the inner type.
        if ($type !== null && strtolower($format ?? '') === self::ENCRYPTED_PREFIX) {
            $innerType = $type;
        }
        // If the type is given as "encrypted:string", "encrypted:array", etc., then we need to parse the
        // inner type from the "format" argument, which will be extracted from the string after the colon in the type string.
        else if (strtolower($typeString) === self::ENCRYPTED_PREFIX) {
            $innerType = CastType::fromString($format);
        }

        if (!isset($innerType)) {
            return null;
        }

        if (!in_array($innerType, self::ENCRYPTABLE_TYPES, true)) {
            throw InvalidCastTypeException::forInvalidEncryptedType(
                $innerType->value,
                array_map(static fn(CastType $t) => strtolower($t->value), self::ENCRYPTABLE_TYPES)
            );
        }

        return [$innerType];
    }

    /**
     * @inheritDoc
     */
    public static function argsForProperty(\ReflectionProperty $prop): array|null
    {
        // We can not infer the inner type from the property type declaration.
        return null;
    }
}
