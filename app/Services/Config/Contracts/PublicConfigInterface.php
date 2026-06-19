<?php
declare(strict_types=1);


namespace App\Services\Config\Contracts;


use App\Services\Config\AbstractConfig;
use Illuminate\Http\Request;

interface PublicConfigInterface
{
    /**
     * @see AbstractConfig::namespace()
     */
    public static function namespace(): string;

    /**
     * A unique key for this config in the public API. Should be snake_case and not contain the namespace prefix.
     * @return string
     */
    public static function publicKey(): string;

    /**
     * Convert the config to an array suitable for public API output. Return null if this config should not be exposed.
     * Ensure that the output does not contain any sensitive information. You can use the request to customize the output based on the user's permissions.
     * If you return null, this config will be omitted from the public API response.
     * @param Request $request
     * @return array|null
     */
    public function toPublicArray(Request $request): array|null;
}
