<?php

namespace App\Models;

use App\Casts\AsAsymmetricPublicKeyCast;
use App\Casts\AsHybridCryptoValueCast;
use App\Events\ExtAppUserCreatedEvent;
use App\Events\ExtAppUserRemovedEvent;
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
        'ext_app_id',
        'user_id',
        'personal_access_token_id',
        'api_token'
    ];
    
    protected $casts = [
        'user_public_key' => AsAsymmetricPublicKeyCast::class,
        'user_private_key' => AsHybridCryptoValueCast::class,
        'api_token' => AsHybridCryptoValueCast::class,
    ];
    
    protected $dispatchesEvents = [
        'created' => ExtAppUserCreatedEvent::class,
        'deleted' => ExtAppUserRemovedEvent::class
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
}
