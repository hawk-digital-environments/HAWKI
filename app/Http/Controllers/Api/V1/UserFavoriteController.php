<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UserFavoriteValue;
use App\Services\Users\Favorites\UserFavoritesService;
use LaravelJsonApi\Core\Responses\DataResponse;
use LaravelJsonApi\Laravel\Http\Controllers\Actions;
use LaravelJsonApi\Laravel\Http\Requests\ResourceQuery;
use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

/**
 * Controller of the `user-favorites` JSON:API resource.
 *
 * The route is protected by `auth:sanctum` (see `routes/api.php`) — favorites
 * exist per user account, guests get a standard 401.
 *
 * Store goes through the {@see UserFavoritesService} (via the `creating` hook,
 * which replaces the default repository create) to get idempotent semantics:
 * POSTing an already-existing favorite returns the existing row instead of a 409.
 *
 * Destroy keeps the standard JSON:API delete, but the `deleting` hook verifies
 * ownership first: the model's contextual scope does not apply to the route
 * binding, so without the hook a user could delete another user's row by id.
 * The check answers 404 (not 403) — a foreign favorite id is indistinguishable
 * from a nonexistent one.
 */
class UserFavoriteController extends Controller
{
    use Actions\FetchMany;
    use Actions\Store;
    use Actions\Destroy;

    public function __construct(private readonly UserFavoritesService $favoritesService)
    {
    }

    /**
     * Idempotent create: delegates to the favorites service, which returns the
     * existing row when the (namespace, item_type, identifier, user) quadruple
     * already exists.
     */
    public function creating(ResourceRequest $request, ResourceQuery $query): DataResponse
    {
        $validated = $request->validated();

        $favorite = $this->favoritesService->markAsFavorite(
            $validated['item_type'],
            $validated['identifier'],
            $validated['namespace'] ?? null,
        );

        return DataResponse::make($favorite)
            ->withQueryParameters($query)
            ->didCreate();
    }

    /**
     * Ownership check before the default delete proceeds (returning nothing).
     * Purely row-based: the user id column comparison avoids hydrating the
     * related user model.
     */
    public function deleting(UserFavoriteValue $favorite, ResourceRequest $request): void
    {
        abort_unless(
            $request->user()?->id === $favorite->user_id,
            404,
            'This favorite does not exist.',
        );
    }
}
