<?php

namespace App\Models;

use App\Casts\AsAsymmetricPublicKey;
use App\Events\ExtExtAppCreatedEvent;
use App\Events\ExtExtAppRemovedEvent;
use App\Policies\ExtAppPolicy;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UsePolicy(ExtAppPolicy::class)]
class ExtApp extends Model
{
    /**
     * The "employeetype" for the user that is created for the app.
     */
    public const string EMPLOYEE_TYPE_APP = 'app';

    /**
     * The name of the sanctum token that is used for the app.
     */
    public const string APP_TOKEN_NAME = 'app';

    /**
     * The name of the sanctum token that is used for the users of the app.
     */
    public const string APP_USER_TOKEN_NAME_PREFIX = 'External App User';

    /**
     * If {@see self::get_healthy_status()} returns this status, the app is healthy and all dependencies are present.
     */
    public const string HEALTHY_STATUS = 'healthy';

    /**
     * If {@see self::get_healthy_status()} returns this status, the app is healthy, but the user account of the app is deleted.
     * The difference to {@see self::HEALTHY_STATUS_MISSING_USER} is that the user account exists, but is marked as deleted.
     */
    public const string HEALTHY_STATUS_DELETED_USER = 'deleted_user';

    /**
     * If {@see self::get_healthy_status()} returns this status, the app is healthy, but the user account of the app is missing.
     */
    public const string HEALTHY_STATUS_MISSING_USER = 'missing_user';

    /**
     * If {@see self::get_healthy_status()} returns this status, the app is healthy, but the user token of the app is missing.
     * This means that the user account exists, but the token for the app is not present.
     */
    public const string HEALTHY_STATUS_MISSING_USER_TOKEN = 'missing_user_token';

    use SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'url',
        'logo_url',
        'app_public_key',
        'redirect_url',
        'app_user_id'
    ];

    protected $dispatchesEvents = [
        'created' => ExtExtAppCreatedEvent::class,
        'deleted' => ExtExtAppRemovedEvent::class
    ];

    protected $casts = [
        'app_public_key' => AsAsymmetricPublicKey::class,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function appUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'app_user_id');
    }

    /**
     * @return HasMany<ExtAppUser, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(ExtAppUser::class, 'app_id');
    }

    /**
     * Checks if all required dependencies of the app are present and configured as expected.
     * @return string Returns one of the {@see HEALTHY_STATUS} constants.
     */
    public function get_healthy_status(): string
    {
        $user = $this->appUser;
        if (!$user) {
            return self::HEALTHY_STATUS_MISSING_USER;
        }

        if ($user->isRemoved) {
            return self::HEALTHY_STATUS_DELETED_USER;
        }

        if (!$user->tokens->where('name', 'app')->first()) {
            return self::HEALTHY_STATUS_MISSING_USER_TOKEN;
        }

        return self::HEALTHY_STATUS;
    }
}
