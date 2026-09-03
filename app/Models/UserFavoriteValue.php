<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Scopes\Generic\BelongsToUserScope;
use App\Services\System\Database\Eloquent\ContextualScopes\HasContextualScopesTrait;
use App\Services\System\Database\Eloquent\ContextualScopes\ScopeRegistrar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One favorited item of one user, addressed by the (`namespace`, `type`, `identifier`)
 * triple — e.g. namespace `hawki-core`, type `ai-model`, identifier of the model.
 *
 * Access control: the `'access'` contextual scope confines every query to the
 * authenticated user as defense-in-depth; the owning services pass the user
 * explicitly as well.
 */
class UserFavoriteValue extends Model
{
    use HasContextualScopesTrait;

    /**
     * Default page size for this model's paginated queries. Favorites are small
     * sets (dozens, not thousands), so the `user-favorites` index can deliver
     * the whole list in one page.
     */
    protected $perPage = 100;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'namespace',
        'type',
        'identifier',
        'user_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * {@inheritDoc}
     */
    protected static function registerScopes(ScopeRegistrar $registrar): void
    {
        $registrar->addScope('access', new BelongsToUserScope());
    }
}
