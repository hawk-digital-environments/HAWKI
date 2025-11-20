<?php

namespace App\Casts;

use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsHybridCryptoValueCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): HybridCryptoValue
    {
        return HybridCryptoValue::fromString($value);
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof HybridCryptoValue) {
            return (string)$value;
        }
        return $value;
    }
}
