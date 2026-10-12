<?php

declare(strict_types=1);

namespace App\Services\Users\Settings\Values;

use App\Services\Users\Settings\AbstractUserSettings;

/**
 * One namespace's user-settings instances, keyed by their public key — the object
 * behind a single namespace-scoped `user-settings` JSON:API resource
 * (`GET /api/hawki/v1/user-settings/{namespace}`).
 *
 * The instances are hydrated per caller through
 * {@see \App\Services\Users\Settings\UserSettingsService}, so a guest's aggregate holds
 * session/runtime-backed instances and an authenticated user's aggregate holds
 * database-backed ones.
 */
final readonly class NamespacedUserSettings
{
    /**
     * @param string                              $namespace the namespace the aggregate represents, e.g. `'hawki-core'`
     * @param array<string, AbstractUserSettings> $settings  the namespace's settings instances, keyed by public key
     */
    public function __construct(
        public string $namespace,
        public array $settings,
    ) {
    }

    /**
     * Returns the instance for a public key, or null when the namespace has no settings
     * class with that key.
     */
    public function get(string $publicKey): ?AbstractUserSettings
    {
        return $this->settings[$publicKey] ?? null;
    }

    /**
     * Returns all public keys present in this aggregate.
     *
     * @return list<string>
     */
    public function publicKeys(): array
    {
        return array_keys($this->settings);
    }
}
