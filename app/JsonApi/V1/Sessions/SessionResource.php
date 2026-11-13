<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions;

use App\Services\Auth\Value\Session;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property Session $resource
 */
class SessionResource extends JsonApiResource
{
    public function id(): string
    {
        return $this->resource->id;
    }

    public function attributes($request): iterable
    {
        return [
        ];
    }

    public function relationships($request): iterable
    {
        return [
        ];
    }
}
