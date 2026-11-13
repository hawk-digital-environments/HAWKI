<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Registrations;

use App\Services\Auth\Value\Registration;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property Registration $resource
 */
class RegistrationResource extends JsonApiResource
{
    public function id(): string
    {
        return $this->resource->id;
    }

    public function attributes($request): iterable
    {
        return [
            'policy' => $this->resource->policy?->toArray(),
            'consent' => $this->resource->consent->value,
        ];
    }

    public function relationships($request): iterable
    {
        return [
        ];
    }
}
