<?php

declare(strict_types=1);

namespace App\Services\System\Http\Attributes;

/**
 * Marks a public property of a config/settings class as fillable from request data and
 * declares its validation rule.
 *
 * The attribute is the **single rule source** for request validation: the generic
 * extraction {@see rulesFor()} collects the rules of a class, and the consumer (e.g. a
 * JSON:API `ResourceRequest` whose `rules()` are built dynamically) hands them to the
 * framework's validator — no bespoke request class per config/settings class.
 *
 * Properties *without* the attribute are **never fillable from request data**: only
 * keys with rules appear in validated data, so un-annotated properties cannot reach a
 * write path. The attribute doubles as mass-assignment protection.
 *
 * Attribute arguments must be constant expressions, so rules are **pipe strings** —
 * `Rule::enum()` builder objects are not representable. Enum properties express their
 * allowed set as `in:a,b` over the backed values.
 *
 * Usage:
 * ```php
 * class CoreUserSettings extends AbstractUserSettings
 * {
 *     #[ValidateInput('sometimes|nullable|string|max:5')]
 *     public string|null $locale = null;
 *
 *     #[ValidateInput('sometimes|in:auto,light,dark')]
 *     public Theme $theme = Theme::Auto;
 * }
 * ```
 *
 * @api
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class ValidateInput
{
    /**
     * Per-class cache of extracted rule maps, keyed by class name.
     *
     * @var array<class-string, array<string, string>>
     */
    private static array $rulesCache = [];

    /**
     * @param string $rule Laravel validation rule string, e.g. `'sometimes|in:light,dark'`.
     *                     Start with `sometimes` so partial updates validate only the
     *                     present keys.
     */
    public function __construct(public readonly string $rule)
    {
    }

    /**
     * Generic rule extraction: property name → rule for every public non-static
     * property of $class carrying the attribute. The reflection result is cached
     * statically per class, so repeated calls carry no reflection overhead.
     *
     * Keys of the returned map are exactly the fillable properties of the class.
     *
     * @param class-string $class
     *
     * @return array<string, string>
     */
    public static function rulesFor(string $class): array
    {
        if (isset(self::$rulesCache[$class])) {
            return self::$rulesCache[$class];
        }

        $rules = [];

        foreach ((new \ReflectionClass($class))->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $attributes = $property->getAttributes(self::class);

            if ([] === $attributes) {
                continue;
            }

            $rules[$property->getName()] = $attributes[0]->newInstance()->rule;
        }

        return self::$rulesCache[$class] = $rules;
    }

    /**
     * Testing helper to clear the rule-extraction cache. Not intended for production use.
     */
    public static function reset(): void
    {
        self::$rulesCache = [];
    }
}
