<?php

namespace App\Models;

use Hawk\HawkiCrypto\Value\AsymmetricPublicKey;
use Hawk\HawkiCrypto\Value\HybridCryptoValue;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Sanctum\PersonalAccessToken;

class ExtAppUser extends Model
{
    protected $fillable = [
        'user_public_key',
        'user_private_key',
        'ext_user_id',
        'passkey',
        'app_id',
        'user_id',
        'personal_access_token_id',
        'api_token'
    ];
    
    public function app(): BelongsTo
    {
        return $this->belongsTo(ExtApp::class);
    }
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
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
            set: static fn(HybridCryptoValue $key) => (string)$key
        );
    }
    
    protected function apiToken(): Attribute
    {
        return Attribute::make(
            get: static fn(string $value) => HybridCryptoValue::fromString($value),
            set: static fn(HybridCryptoValue $token) => (string)$token
        );
    }
}
