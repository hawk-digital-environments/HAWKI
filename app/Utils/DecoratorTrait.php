<?php

declare(strict_types=1);


namespace App\Utils;

/**
 * This trait provides functionality to create a decorated instance of a class
 * by inheriting all properties from a given parent object.
 *
 * The decorated class must extend the class of the parent object.
 * The idea is to allow overriding specific methods while retaining the state,
 * of the parent object. Which is useful if you want to modify behavior without
 * changing the original class.
 *
 * Example usage:
 * ```
 * class ParentClass {
 *     private $prop1;
 *     protected $prop2;
 *     public $prop3;
 *
 *     public function baz() {
 *        return $this->prop3;
 *     }
 *
 *     public function foo() {
 *         return $this->prop1;
 *     }
 * }
 *
 * class DecoratedClass extends ParentClass {
 *     use DecoratorTrait;
 *
 *     public function foo() {
 *         return 'overridden';
 *     }
 * }
 *
 * $parent = new ParentClass();
 * $parent->prop3 = 'value';
 *
 * $decorated = DecoratedClass::createDecoratedOf($parent);
 *
 * echo $decorated->foo(); // outputs 'overridden'
 * echo $decorated->baz(); // outputs 'value'
 * ```
 * @api
 */
trait DecoratorTrait
{
    /**
     * Creates a new instance of the class using all properties of the given parent object.
     * This includes all private and protected properties, as well as static properties.
     *
     * Properties the decorating class itself declares (which the parent therefore cannot
     * provide) can be supplied via $additionalProperties, keyed by property name.
     *
     * @param object $parent
     * @param array<string, mixed> $additionalProperties
     * @return static
     */
    public static function createDecoratedOf(object $parent, array $additionalProperties = []): static
    {
        $myClass = static::class;
        // Check if the parent object is of the same class as the parent class of $this
        $parentClass = get_parent_class($myClass);
        if ($parentClass === false) {
            throw new \LogicException(sprintf(
                'Class %s must extend another class to use %s; I would expect you want to extend: %s',
                $myClass,
                __TRAIT__,
                get_class($parent)
            ));
        }

        if (!($parent instanceof $parentClass)) {
            throw new \InvalidArgumentException(sprintf(
                'When inheriting all properties, the parent object must be an instance of %s, %s given.',
                $parentClass,
                get_class($parent)
            ));
        }

        // Create new instance without calling constructor
        $instance = (new \ReflectionClass($myClass))->newInstanceWithoutConstructor();

        // Walk up the inheritance chain to get ALL properties
        $sourceReflection = new \ReflectionObject($parent);
        $targetReflection = new \ReflectionObject($instance);

        do {
            foreach ($sourceReflection->getProperties() as $sourceProperty) {
                // We must iterate over all parent classes of the target to find the property
                // because it might be declared as private in a parent class.
                $localTargetReflection = $targetReflection;
                do {
                    if ($localTargetReflection->hasProperty($sourceProperty->getName())) {
                        break;
                    }

                } while ($localTargetReflection = $localTargetReflection->getParentClass());

                // Skip if we didn't find the property in the target class hierarchy
                if (!$localTargetReflection) {
                    continue;
                }

                $targetProperty = $localTargetReflection->getProperty($sourceProperty->getName());

                if ($sourceProperty->isStatic()) {
                    if (!$sourceProperty->isInitialized(null)) {
                        continue;
                    }
                    $value = $sourceProperty->getValue();
                    $targetProperty->setValue(null, $value);
                    continue;
                }

                if ($sourceProperty->isReadOnly()) {
                    throw new \LogicException(sprintf(
                        'Cannot inherit read-only property %s::$%s from parent class %s',
                        $sourceReflection->getName(),
                        $sourceProperty->getName(),
                        $sourceReflection->getName()
                    ));
                }

                if (!$sourceProperty->isInitialized($parent)) {
                    continue;
                }
                $value = $sourceProperty->getValue($parent);
                $targetProperty->setValue($instance, $value);
            }
        } while ($sourceReflection = $sourceReflection->getParentClass());

        foreach ($additionalProperties as $propertyName => $value) {
            // Additional properties are meant for properties the decorating class adds on
            // top of the parent, so they must exist in the target class hierarchy.
            $localTargetReflection = $targetReflection;
            $targetProperty = null;
            do {
                if ($localTargetReflection->hasProperty($propertyName)) {
                    $targetProperty = $localTargetReflection->getProperty($propertyName);
                    break;
                }
            } while ($localTargetReflection = $localTargetReflection->getParentClass());

            if ($targetProperty === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot assign additional property %s::$%s via %s: the property does not exist.',
                    $myClass,
                    $propertyName,
                    __TRAIT__
                ));
            }

            // Assigning a property the parent already declares would overwrite inherited state.
            $localSourceReflection = new \ReflectionObject($parent);
            do {
                if ($localSourceReflection->hasProperty($propertyName)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Cannot assign additional property %s::$%s via %s: the property is already declared on %s and would overwrite inherited state.',
                        $myClass,
                        $propertyName,
                        __TRAIT__,
                        $localSourceReflection->getName()
                    ));
                }
            } while ($localSourceReflection = $localSourceReflection->getParentClass());

            if ($targetProperty->isStatic()) {
                throw new \InvalidArgumentException(sprintf(
                    'Cannot assign additional property %s::$%s via %s: static properties are not supported.',
                    $myClass,
                    $propertyName,
                    __TRAIT__
                ));
            }

            if ($targetProperty->isReadOnly()) {
                throw new \LogicException(sprintf(
                    'Cannot assign read-only property %s::$%s as an additional property via %s',
                    $myClass,
                    $propertyName,
                    __TRAIT__
                ));
            }

            $targetProperty->setValue($instance, $value);
        }

        return $instance;
    }
}
