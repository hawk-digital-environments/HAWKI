<?php

namespace App\JsonApi\V1\Auths;

use App\Services\Auth\Value\AuthInfo;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property AuthInfo $resource
 */
class AuthResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return $this->resource->id;
    }

    /**
     * Get the resource's attributes.
     *
     * Every attribute is always present, `null` where it does not apply. Unlike the connection
     * resource, nulls are not filtered out here: the frontend switches on `mode`/`start_url` as
     * a pair, and an attribute that silently disappears is far harder to narrow against than one
     * that is explicitly null.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'mode' => $this->resource->mode->value,
            'start_url' => $this->resource->startUrl,
            'capabilities' => get_object_vars($this->resource->capabilities),
            'last_error' => $this->resource->lastError,
        ];
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     */
    public function relationships($request): iterable
    {
        return [
        ];
    }
}
