<?php

namespace App\JsonApi\V1\Connections;

use App\Services\Frontend\Connection\Values\Connection;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property Connection $resource
 */
class ConnectionResource extends JsonApiResource
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
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return array_filter([
            'type' => $this->resource->type->value,
            'version' => $this->resource->version,
            'locale' => $this->resource->locale->lang,
            'userinfo' => $this->resource->userinfo ? get_object_vars($this->resource->userinfo) : null,
            'ext_app_secrets' => $this->resource->extAppSecrets
                ? get_object_vars($this->resource->extAppSecrets)
                : null,
            'ext_app_connect_request' => $this->resource->extAppConnectRequest,
            'migrations_to_apply' => $this->resource->migrationsToApply
        ]);
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
