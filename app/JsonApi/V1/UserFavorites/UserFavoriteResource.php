<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserFavorites;

use App\Models\UserFavoriteValue;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property UserFavoriteValue $resource
 */
class UserFavoriteResource extends JsonApiResource
{
    /**
     * Get the resource's attributes.
     *
     * Only the addressing triple is exposed — `user_id` is implicit (the resource
     * always belongs to the requesting user, enforced by the 'access' scope). The
     * model's `type` column is exposed as `item_type` (JSON:API reserves `type`).
     *
     * @param null|Request $request
     */
    public function attributes($request): iterable
    {
        yield from [
            'namespace' => $this->resource->namespace,
            'item_type' => $this->resource->type,
            'identifier' => $this->resource->identifier,
        ];
    }

    /**
     * Get the resource's relationships.
     *
     * @param null|Request $request
     */
    public function relationships($request): iterable
    {
        yield from [];
    }
}
