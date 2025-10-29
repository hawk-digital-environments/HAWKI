<?php

namespace App\Casts;

use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsAsymmetricPublicKeyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): AsymmetricPublicKey
    {
        return AsymmetricPublicKey::fromString($value);
    }
    
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof AsymmetricPublicKey) {
            return (string)$value;
        }
        return $value;
    }
}
