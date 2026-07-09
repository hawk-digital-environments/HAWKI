<?php

namespace App\Models;

use App\Casts\AsAsymmetricPublicKey;
use App\Casts\AsHybridCryptoValue;
use App\Services\ExtApp\Events\ExtAppUserCreatedEvent;
use App\Services\ExtApp\Events\ExtAppUserRemovedEvent;
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
        'user_public_key' => AsAsymmetricPublicKey::class,
        'user_private_key' => AsHybridCryptoValue::class,
        'api_token' => AsHybridCryptoValue::class,
    ];

    protected $dispatchesEvents = [
        'created' => ExtAppUserCreatedEvent::class,
        'deleted' => ExtAppUserRemovedEvent::class
    ];

    /**
     * @return BelongsTo<ExtApp, $this>
     */
    public function app(): BelongsTo
    {
        return $this->belongsTo(ExtApp::class, 'ext_app_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function personalAccessToken(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class);
    }
}
