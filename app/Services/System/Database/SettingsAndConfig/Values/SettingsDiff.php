<?php

declare(strict_types=1);

namespace App\Services\System\Database\SettingsAndConfig\Values;

/**
 * The result of comparing a settings object against its class-default instance: the
 * properties whose values differ between both.
 *
 * Used by the diff-based persistence: differing properties are written to storage,
 * properties that match the defaults again are removed from storage (sparse storage —
 * a row exists for a user iff the value differs from the class default).
 */
final readonly class SettingsDiff
{
    /**
     * @param list<string> $differingProperties property names whose typed values differ
     */
    public function __construct(public array $differingProperties)
    {
    }

    /**
     * Returns true when the given property differs between the compared objects.
     */
    public function isDifferent(string $property): bool
    {
        return \in_array($property, $this->differingProperties, true);
    }

    /**
     * Returns true when the compared objects differ in at least one property.
     */
    public function differs(): bool
    {
        return [] !== $this->differingProperties;
    }
}
