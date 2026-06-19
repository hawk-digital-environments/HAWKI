<?php

namespace App\Casts;

use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a string to an AsymmetricPublicKey object and vice versa.
 */
class AsAsymmetricPublicKey implements CastsAttributes
{
    /**
     * @inheritDoc
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): AsymmetricPublicKey
    {
        return AsymmetricPublicKey::fromString($value);
    }

    /**
     * @inheritDoc
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof AsymmetricPublicKey) {
            return (string)$value;
        }
        return $value;
    }
}
