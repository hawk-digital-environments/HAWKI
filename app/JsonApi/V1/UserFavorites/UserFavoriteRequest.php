<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserFavorites;

use LaravelJsonApi\Laravel\Http\Requests\ResourceRequest;

/**
 * Validation for the `user-favorites` resource.
 *
 * `item_type` and `identifier` are required — they address the favorited item.
 * (`item_type` is the wire name of the model's `type` column; JSON:API reserves
 * the member name `type`.) `namespace` is optional and defaults server-side to
 * {@see \App\Services\Users\Favorites\UserFavoritesService::DEFAULT_NAMESPACE}.
 * All three are free-form strings with format/length constraints only: there is
 * deliberately no registry of allowed namespaces/types.
 */
class UserFavoriteRequest extends ResourceRequest
{
    /**
     * Get the validation rules for the resource.
     */
    public function rules(): array
    {
        return [
            'namespace' => ['sometimes', 'nullable', 'string', 'max:191'],
            'item_type' => ['required', 'string', 'max:191'],
            'identifier' => ['required', 'string', 'max:191'],
        ];
    }
}
