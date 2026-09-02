<?php
declare(strict_types=1);

namespace App\Services\PhpStan;

use App\Utils\DecoratorTrait;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Reflection\ReflectionProvider;
use PHPStan\Rules\Properties\ReadWritePropertiesExtension;

/**
 * Teaches PHPStan that properties declared by a class using {@see DecoratorTrait} may be
 * populated by the trait's reflection machinery ({@see DecoratorTrait::createDecoratedOf()}
 * additional properties) instead of explicit constructor assignment.
 *
 * Without this, PHPStan reports "property is never written, only read" for decorator-added
 * dependencies, even though createDecoratedOf() guarantees population when the decorator is
 * built. Properties inherited from the decorated parent are unaffected: their declaring
 * classes do not use the trait.
 *
 * Marking the properties as always-written and always-initialized silences that check while
 * leaving the rest of the property analysis intact.
 */
final class DecoratorTraitPropertiesExtension implements ReadWritePropertiesExtension
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
        return $this->isDeclaredByDecoratorTraitUser($property);
    }

    public function isInitialized(PropertyReflection $property, string $propertyName): bool
    {
        return $this->isDeclaredByDecoratorTraitUser($property);
    }

    private function isDeclaredByDecoratorTraitUser(PropertyReflection $property): bool
    {
        if (!$this->reflectionProvider->hasClass(DecoratorTrait::class)) {
            return false;
        }

        foreach ($property->getDeclaringClass()->getTraits() as $trait) {
            if ($trait->getName() === DecoratorTrait::class) {
                return true;
            }
        }

        return false;
    }
}
