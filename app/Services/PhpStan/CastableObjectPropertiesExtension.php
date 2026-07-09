<?php
declare(strict_types=1);

namespace App\Services\PhpStan;

use App\Utils\Casts\AbstractCastableObject;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * Teaches PHPStan that public properties on {@see AbstractCastableObject} subclasses are
 * populated externally by the hydration machinery (reflection-based property assignment in
 * the constructor), not via explicit constructor assignment.
 *
 * Without this, PHPStan reports "uninitialized readonly property ... assign it in the
 * constructor" for every readonly property on a castable value object, even though the
 * base class guarantees population through {@see AbstractCastableObject::fromStringArray()}
 * and {@see AbstractCastableObject::fromArray()}.
 *
 * Marking the properties as always-written and always-initialized silences that check while
 * leaving the readonly/dead-property analysis intact for everything else.
 */
final class CastableObjectPropertiesExtension implements ReadWritePropertiesExtension
{
    public function __construct(private readonly ReflectionProvider $reflectionProvider)
    {
    }

    public function isAlwaysRead(PropertyReflection $property, string $propertyName): bool
    {
        return false;
    }

    public function isAlwaysWritten(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isCastableObjectProperty($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isCastableObjectProperty($property);
    }

    private function isCastableObjectProperty(PropertyReflection $property): bool
    {
        if (!$this->reflectionProvider->hasClass(AbstractCastableObject::class)) {
            return false;
        }

        return $property->getDeclaringClass()->isSubclassOfClass(
            $this->reflectionProvider->getClass(AbstractCastableObject::class),
        );
    }
}
