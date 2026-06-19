<?php

namespace App\Casts;

use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a string to a HybridCryptoValue and vice versa.
 */
class AsHybridCryptoValue implements CastsAttributes
{

    /**
     * @inheritDoc
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): HybridCryptoValue
    {
        return HybridCryptoValue::fromString($value);
    }

    /**
     * @inheritDoc
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof HybridCryptoValue) {
            return (string)$value;
        }
        return $value;
    }
}
