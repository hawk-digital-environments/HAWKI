<?php

namespace App\Models;

use App\Models\Scopes\Generic\BelongsToUserScope;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSettingValue extends Model
{
    use HasContextualScopesTrait;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'namespace',
        'key',
        'value',
        'user_id',
    ];

    /**
     * @inheritDoc
     */
    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', new BelongsToUserScope());
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
