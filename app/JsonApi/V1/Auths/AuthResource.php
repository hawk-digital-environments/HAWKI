<?php

namespace App\JsonApi\V1\Auths;

use App\Services\Auth\Value\AuthInfo;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Document\Link;
use LaravelJsonApi\Core\Document\Links;
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
     * `last_error` is a nullable stable code: invalid_credentials or provider_failed.
     *
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'mode' => $this->resource->mode->value,
            'capabilities' => get_object_vars($this->resource->capabilities),
            'last_error' => $this->resource->lastError,
        ];
    }

    /**
     * The redirect link is present exactly when `mode` is `redirect`: navigating the browser
     * there starts the identity provider round trip. The frontend never hardcodes the URL.
     *
     * @param Request|null $request
     */
    public function links($request): Links
    {
        $links = new Links();

        if ($this->resource->startUrl !== null) {
            $links->push(new Link('redirect', $this->resource->startUrl));
        }

        return $links;
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
