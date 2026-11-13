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
        $policy = $this->resource->policy;

        return [
            'policy' => $policy === null ? null : [
                'id' => (string)$policy->id,
                'locale' => $policy->locale,
                'text' => $policy->text,
                'hash' => $policy->hash,
            ],
            'consent' => $this->resource->consent,
        ];
    }

    public function relationships($request): iterable
    {
        return [
        ];
    }
}
