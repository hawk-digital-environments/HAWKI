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
 */
trait DecoratorTrait
{
    /**
     * Creates a new instance of the class using all properties of the given parent object.
     * This includes all private and protected properties, as well as static properties.
     *
     * @param object $parent
     * @return static
     */
    public static function createDecoratedOf(object $parent): static
    {
        $myClass = static::class;
        // Check if the parent object is of the same class as the parent class of $this
        $parentClass = get_parent_class($myClass);
        if ($parentClass === false || !($parent instanceof $parentClass)) {
            throw new \InvalidArgumentException(sprintf(
                'When inheriting all properties, the parent object must be an instance of %s, %s given.',
                $parentClass,
                get_class($parent)
            ));
        }

        // Create new instance without calling constructor
        $instance = (new \ReflectionClass($myClass))->newInstanceWithoutConstructor();

        $reflection = new \ReflectionObject($parent);
        $selfReflection = new \ReflectionObject($instance);

        foreach ($reflection->getProperties() as $property) {
            $property->setAccessible(true);
            $selfProperty = $selfReflection->getProperty($property->getName());
            $selfProperty->setAccessible(true);

            $selfProperty->setValue($instance, $property->getValue($parent));
        }

        return $instance;
    }
}
