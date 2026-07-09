<?php
declare(strict_types=1);

namespace App\Casts;

use App\Casts\Contracts\CastableInstanceInterface;
use App\Casts\Exceptions\InvalidCastConfigurationException;
use App\Casts\Exceptions\InvalidCastValueException;
use Illuminate\Contracts\Database\Eloquent\Castable;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * A generic, reusable Eloquent cast that serializes any object implementing
 * {@see CastableInstanceInterface} to and from a JSON string in the database.
 *
 * ## Usage
 *
 * In your Eloquent model's `casts()` method, use the {@see AsInstance::of()} helper
 * to get the correct cast string for your value object class:
 *
 * ```php
 * protected function casts(): array
 * {
 *     return [
 *         'io_list' => AsInstance::of(ModelIoList::class),
 *     ];
 * }
 * ```
 *
 * ## Requirements
 *
 * The target class **must** implement {@see CastableInstanceInterface}, which requires:
 *
 * - `static fromArray(array $data): static` — hydrates an instance from a plain array
 * - `toArray(): array` — serializes the instance back to a plain array
 *
 * The database column stores the value as a JSON-encoded array.
 *
 * ## How it works
 *
 * `AsInstance` implements Laravel's `Castable` contract and returns an anonymous
 * `CastsAttributes` implementation from `castUsing()`. The target class is passed
 * as a base64-encoded argument so it survives Laravel's colon-based cast-string parsing.
 * Use {@see AsInstance::of()} instead of building the cast string manually.
 *
 * @see CastableInstanceInterface
 * @see \Illuminate\Contracts\Database\Eloquent\Castable
 */
class AsInstance implements Castable
{
    private static array $dynamicClassNameResolvers = [];

    /**
     * @inheritDoc
     *
     * @throws InvalidCastConfigurationException when the class argument is missing, unknown, or invalid
     */
    public static function castUsing(array $arguments): CastsAttributes
    {
        if (empty($arguments)) {
            throw InvalidCastConfigurationException::forMissingClassArgument();
        }

        $classNameResolver = function ($model, string $key, $value, array $attributes) use ($arguments) {
            $className = base64_decode($arguments[0]);
            if (isset(self::$dynamicClassNameResolvers[$className])) {
                $resolver = self::$dynamicClassNameResolvers[$className];
                return $resolver($model, $key, $value, $attributes);
            }
            if (!class_exists($className)) {
                throw InvalidCastConfigurationException::forUnknownClass($className);
            }
            if (!in_array(CastableInstanceInterface::class, class_implements($className), true)) {
                throw InvalidCastConfigurationException::forMissingInterface($className);
            }

            return $className;
        };

        return new readonly class($classNameResolver) implements CastsAttributes {
            public function __construct(private \Closure $classNameResolver)
            {
            }

            /**
             * @inheritDoc
             *
             * @throws InvalidCastValueException when the stored value is not a valid JSON string
             */
            public function get($model, string $key, $value, array $attributes): CastableInstanceInterface
            {
                if ($value === null) {
                    // Treat null values as empty arrays (default for json columns in MySQL)
                    $value = '[]';
                }

                if (!is_string($value)) {
                    throw InvalidCastValueException::forNonStringDatabaseValue();
                }

                $data = json_decode($value, true);
                if (!is_array($data)) {
                    // Silent fix for "null" values (default for json columns in MySQL) by treating them as empty arrays
                    if (empty($data) || $data === 'null') {
                        $data = [];
                    } else {
                        throw InvalidCastValueException::forInvalidJson();
                    }
                }

                $className = ($this->classNameResolver)($model, $key, $value, $attributes);

                if (!is_subclass_of($className, CastableInstanceInterface::class)) {
                    throw InvalidCastConfigurationException::forMissingInterface($className);
                }

                return ($className)::fromArray($data);
            }

            /**
             * @inheritDoc
             *
             * @throws InvalidCastValueException when the given value does not implement {@see CastableInstanceInterface}
             */
            public function set($model, string $key, $value, array $attributes): false|string
            {
                if ($value === null) {
                    return '[]';
                }
                if (!$value instanceof CastableInstanceInterface) {
                    throw InvalidCastValueException::forNonCastableInstance($value);
                }

                return json_encode($value->toArray());
            }
        };
    }

    /**
     * Returns the cast string to use in a model's `casts()` method.
     *
     * The class name is base64-encoded to survive Laravel's colon-based argument parsing.
     *
     * ```php
     * 'input' => AsInstance::of(ModelIoList::class),
     * ```
     *
     * Alternatively, you can pass a closure that returns the class name dynamically:
     * ```php
     * 'input' => AsInstance::of(function ($model, $key, $value, $attributes) {
     *     return $model->isSpecial() ? SpecialModelIoList::class : ModelIoList::class;
     * }),
     */
    public static function of(string|\Closure $classOrResolver): string
    {
        if ($classOrResolver instanceof \Closure) {
            $resolverId = 'dynamic_class_resolver_' . spl_object_id($classOrResolver);
            self::$dynamicClassNameResolvers[$resolverId] = $classOrResolver;
            $class = $resolverId;
        } else {
            $class = $classOrResolver;
        }
        return self::class . ':' . base64_encode($class);
    }

    /**
     * @return void
     * @internal This method is intended for testing purposes only. It clears the dynamic class name resolvers.
     */
    public static function clearDynamicResolvers(): void
    {
        self::$dynamicClassNameResolvers = [];
    }
}
