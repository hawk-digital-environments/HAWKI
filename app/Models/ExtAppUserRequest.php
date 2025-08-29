<?php

namespace App\Models;

use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExtAppUserRequest extends Model
{
    protected $fillable = [
        'user_public_key',
        'user_private_key',
        'ext_user_id',
        'request_id',
        'valid_until',
        'app_id',
    ];
    
    public function app(): BelongsTo
    {
        return $this->belongsTo(ExtApp::class);
    }
    
    protected function userPublicKey(): Attribute
    {
        return Attribute::make(
            get: static fn(string $value) => AsymmetricPublicKey::fromString($value),
            set: static fn(AsymmetricPublicKey $key) => (string)$key
        );
    }
    
    protected function userPrivateKey(): Attribute
    {
        return Attribute::make(
            get: static fn(string $value) => HybridCryptoValue::fromString($value),
            set: static fn(HybridCryptoValue $value) => (string)$value
        );
    }
    
    protected function casts(): array
    {
        return [
            'valid_until' => 'datetime',
        ];
    }
}
