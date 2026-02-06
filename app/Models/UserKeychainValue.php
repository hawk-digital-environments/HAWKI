<?php

namespace App\Models;

use App\Casts\AsSymmetricCryptoValueCast;
use App\Events\UserKeychainValueCreatedEvent;
use App\Events\UserKeychainValueDeletingEvent;
use App\Events\UserKeychainValueUpdatedEvent;
use App\Services\User\Keychain\Value\UserKeychainValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserKeychainValue extends Model
{
    protected $fillable = [
        'user_id',
        'key',
        'value',
        'type'
    ];
    
    protected $casts = [
        'value' => AsSymmetricCryptoValueCast::class,
        'type' => UserKeychainValueType::class
    ];
    
    protected $dispatchesEvents = [
        'created' => UserKeychainValueCreatedEvent::class,
        'updated' => UserKeychainValueUpdatedEvent::class,
        'deleting' => UserKeychainValueDeletingEvent::class
    ];
    
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
