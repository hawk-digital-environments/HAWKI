<?php

namespace App\Models;

use App\Casts\AsSymmetricCryptoValue;
use App\Services\Users\Events\UserKeychainValueCreatedEvent;
use App\Services\Users\Events\UserKeychainValueDeletingEvent;
use App\Services\Users\Events\UserKeychainValueUpdatedEvent;
use App\Models\Scopes\Generic\BelongsToUserScope;
use App\Policies\UserKeychainValuePolicy;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use App\Services\Users\Keychain\Value\UserKeychainValueType;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UsePolicy(UserKeychainValuePolicy::class)]
class UserKeychainValue extends Model
{
    use HasContextualScopesTrait;

    protected $fillable = [
        'user_id',
        'key',
        'value',
        'type'
    ];

    protected $casts = [
        'value' => AsSymmetricCryptoValue::class,
        'type' => UserKeychainValueType::class
    ];

    protected $dispatchesEvents = [
        'created' => UserKeychainValueCreatedEvent::class,
        'updated' => UserKeychainValueUpdatedEvent::class,
        'deleting' => UserKeychainValueDeletingEvent::class
    ];

    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', new BelongsToUserScope());
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
