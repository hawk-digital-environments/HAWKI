<?php
declare(strict_types=1);


namespace App\Utils;

/**
 * When used in combination with the {@see \JsonSerializable} interface,
 * this trait provides a default implementation of the jsonSerialize method.
 * It serializes all public properties of the object into an associative array.
 * Static properties are ignored.
 */
trait JsonSerializableTrait
{
    /**
     * Serializes the object to a JSON array.
     * The output will contain all public properties of the object.
     * @return array
     */
    public function jsonSerialize(): array
    {
        $properties = (new \ReflectionClass($this))->getProperties(\ReflectionProperty::IS_PUBLIC);
        $data = [];
        foreach ($properties as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $data[$property->getName()] = $property->isInitialized($this) ? $property->getValue($this) : null;
        }
        return $data;
    }
}
