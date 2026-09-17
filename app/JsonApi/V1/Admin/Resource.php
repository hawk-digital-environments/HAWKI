<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Admin;

use App\Http\Resources\AdminResource;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property Record $resource
 */
final class Resource extends JsonApiResource
{
    public function id(): string
    {
        return $this->resource->id();
    }

    public function attributes($request): iterable
    {
        return (new AdminResource(mb_substr($this->type(), 6), $this->resource->row))->getAttributes();
    }

    public function meta($request): iterable
    {
        return isset($this->resource->row['_version'])
            ? ['version' => $this->resource->row['_version']]
            : [];
    }
}
