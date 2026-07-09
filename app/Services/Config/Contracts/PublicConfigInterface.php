<?php
declare(strict_types=1);


namespace App\Services\Config\Contracts;


use App\Services\Config\AbstractConfig;
use Illuminate\Http\Request;

/**
 * Marks a config class as eligible for inclusion in the public `GET /api/v1/configs` response.
 *
 * Implementing classes must also extend {@see AbstractConfig}. They are registered with
 * {@see \App\Services\Config\Registries\PublicConfigRegistry} in a service provider:
 *
 * ```php
 * $this->app->extend(
 *     PublicConfigRegistry::class,
 *     fn(PublicConfigRegistry $r) => $r->declare(MyConfig::class),
 * );
 * ```
 *
 * The resulting JSON structure groups configs by namespace and then by public key:
 *
 * ```json
 * {
 *   "hawki-core": {
 *     "my_feature": { "setting": "value" }
 *   }
 * }
 * ```
 */
interface PublicConfigInterface
{
    /**
     * The namespace that groups this config in the API response.
     *
     * @see AbstractConfig::namespace()
     */
    public static function namespace(): string;

    /**
     * The unique key for this config within its namespace in the public API response.
     *
     * Must be snake_case and must not include the namespace prefix.
     * Example: `'ai'`, `'storage_files'`, `'salts'`.
     */
    public static function publicKey(): string;

    /**
     * Serializes the config for the public API response.
     *
     * Return `null` to omit this config from the response entirely — use this to gate
     * visibility on authentication or user permissions. Never include sensitive values
     * (secrets, private keys, internal tokens) in the returned array.
     *
     * @param Request $request the current HTTP request, available for permission checks
     */
    public function toPublicArray(Request $request): array|null;
}
