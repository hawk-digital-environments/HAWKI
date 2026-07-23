<?php

declare(strict_types=1);

namespace Tests\Unit\Utils\Casts\Casters\DefaultCasterTestFixtures;

/**
 * Fixture exposing one property per type that DefaultCaster handles,
 * plus one unhandled class-typed property used to verify null is returned.
 */
class DefaultCasterTestConfig
{
    public int $intProp = 0;
    public float $floatProp = 0.0;
    public bool $boolProp = false;
    public string $stringProp = '';
    public array $arrayProp = [];
    public object $objectProp; // matched by DefaultCaster (class name = 'object' keyword)
    public \stdClass $stdClassProp; // not matched by DefaultCaster (class name ≠ 'object' keyword)
    public int|float $unionProp = 0; // union type should be ignored by DefaultCaster
}
