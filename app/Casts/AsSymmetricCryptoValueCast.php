<?php
declare(strict_types=1);


namespace App\Casts;


use Hawk\HawkiCrypto\Value\SymmetricCryptoValue;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsSymmetricCryptoValueCast implements CastsAttributes
{
    /**
     * @inheritDoc
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): SymmetricCryptoValue
    {
        return SymmetricCryptoValue::fromString($value);
    }
    
    /**
     * @inheritDoc
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof SymmetricCryptoValue) {
            return (string)$value;
        }
        return $value;
    }
}
