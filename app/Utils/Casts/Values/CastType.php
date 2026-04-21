<?php

declare(strict_types=1);

namespace App\Utils\Casts\Values;

use App\Utils\Casts\Exceptions\InvalidCastTypeException;

/**
 * Enumeration of all built-in cast types supported by {@see AbstractCastableObject}.
 *
 * Use these cases as the argument to {@see CastedValue} instead of raw strings for
 * IDE autocompletion and compile-time safety:
 *
 * ```php
 * #[CastedValue(CastType::DATETIME)]
 * public Carbon $created_at;
 *
 * #[CastedValue('encrypted:string')]   // still needs a subtype string for encrypted variants
 * ```
 *
 * Parameterised variants (e.g. `'datetime:d.m.Y H:i'`, `'encrypted:string'`) and custom
 * caster class names must still be passed as plain strings because they carry extra
 * arguments that cannot be encoded in an enum case.
 * @see CastedValue
 * @see AbstractCastableObject
 * @api
 */
enum CastType: string
{
    private const ALIASES = [
        'integer' => self::INT,
        'double' => self::FLOAT,
        'real' => self::FLOAT,
        'boolean' => self::BOOL,
    ];

    case INT = 'int';
    case FLOAT = 'float';
    case BOOL = 'bool';
    case STRING = 'string';
    case ARRAY = 'array';
    case JSON = 'json';
    case OBJECT = 'object';
    case DATE = 'date';
    case IMMUTABLE_DATE = 'immutable_date';
    case DATETIME = 'datetime';
    case IMMUTABLE_DATETIME = 'immutable_datetime';
    case TIMESTAMP = 'timestamp';

    /**
     * Tries to resolve a string to a CastType, accepting known aliases (e.g. 'integer' for 'int') and being case-insensitive.
     * Returns null if the string cannot be resolved to any CastType.
     * @param string $value
     * @return self|null
     */
    public static function tryFromString(string $value): self|null
    {
        $lowerValue = strtolower($value);
        return self::ALIASES[$lowerValue] ?? self::tryFrom($lowerValue);
    }

    /**
     * The same as {@see self::from()} but accepts known alias strings (e.g. 'integer' for 'int') and is case-insensitive.
     * @param string $value
     * @return self
     */
    public static function fromString(string $value): self
    {
        $resolved = self::tryFromString($value);
        if ($resolved !== null) {
            return $resolved;
        }
        throw InvalidCastTypeException::forType($value);
    }
}
