<?php

declare(strict_types=1);

namespace App\Services\System\Http;

use App\Services\System\Http\Attributes\ValidateInput;
use App\Utils\Casts\AbstractCastableObject;
use Illuminate\Container\Attributes\Singleton;

/**
 * Maps validated request data onto typed config/settings objects.
 *
 * The mapper is the hydration half of the request pipeline — it never touches the
 * request object and never runs validation itself: rules come from
 * {@see ValidateInput::rulesFor()}, execution from the framework's validator, which
 * hands only *validated* data (and only rule-bearing keys) to the write path.
 *
 * Hydration is delegated entirely to the cast system: validated wire values are
 * filtered to the `#[ValidateInput]`-annotated (fillable) properties and merged into
 * the stored-string map, then {@see AbstractCastableObject::fromStringArray()} runs
 * the standard cast pipeline — non-string values are juggled through each property's
 * cast (serialize via the caster's `set()`, hydrate via its `get()`), so ints, bools,
 * enum names and arrays hydrate exactly like a database round trip.
 *
 * - `map()` — a fresh instance: class defaults + validated overrides.
 * - `mapOnto()` — a rehydrated copy of $onto with the validated properties
 *   overwritten. The current values pass through the same serialize/hydrate round
 *   trip a save performs, which is safe because casters are faithful round trips by
 *   contract (the diff-based persistence already depends on this). The result is a
 *   new instance — $onto stays untouched, letting callers detect "changed" by
 *   instance inequality. Keys not present in $validatedData keep the current values
 *   (JSON:API PATCH semantics: missing attributes keep the current value).
 *
 * @api
 *
 * @see ValidateInput
 */
#[Singleton()]
class RequestToObjectMapper
{
    /**
     * Creates a fresh instance of the class from its defaults plus the validated
     * overrides — only properties carrying a `#[ValidateInput]` attribute are filled.
     *
     * @template T of AbstractCastableObject
     *
     * @param class-string<T>      $objectClass
     * @param array<string, mixed> $validatedData property name → validated value
     *
     * @return T
     */
    public function map(string $objectClass, array $validatedData): AbstractCastableObject
    {
        return $objectClass::fromStringArray($this->filterFillable($objectClass, $validatedData));
    }

    /**
     * Returns a rehydrated copy of $onto with the validated properties overwritten.
     * Only properties carrying a `#[ValidateInput]` attribute are ever filled; keys
     * not present in $validatedData keep the instance's current values.
     *
     * @template T of AbstractCastableObject
     *
     * @param T                    $onto
     * @param array<string, mixed> $validatedData property name → validated value
     *
     * @return T
     */
    public function mapOnto(AbstractCastableObject $onto, array $validatedData): AbstractCastableObject
    {
        return $onto::fromStringArray(array_merge(
            $onto->toStringArray(),
            $this->filterFillable($onto::class, $validatedData),
        ));
    }

    /**
     * Drops every key that is not fillable: only properties carrying a
     * `#[ValidateInput]` attribute may ever be filled from request data. Belt and
     * braces — validated() only returns rule keys, so this rarely triggers — but
     * the mapper is also callable with hand-built maps.
     *
     * @param class-string<AbstractCastableObject> $objectClass
     * @param array<string, mixed>                 $validatedData
     *
     * @return array<string, mixed>
     */
    private function filterFillable(string $objectClass, array $validatedData): array
    {
        $fillable = ValidateInput::rulesFor($objectClass);

        return array_filter(
            $validatedData,
            static fn (string $property): bool => \array_key_exists($property, $fillable),
            \ARRAY_FILTER_USE_KEY,
        );
    }
}
