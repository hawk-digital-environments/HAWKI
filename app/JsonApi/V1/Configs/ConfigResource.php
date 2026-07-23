<?php

namespace App\JsonApi\V1\Configs;

use App\Services\Config\Registries\PublicConfigRegistry;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property PublicConfigRegistry $resource
 */
class ConfigResource extends JsonApiResource
{
    public function id(): string
    {
        return 'default';
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     * @return iterable
     */
    public function attributes($request): iterable
    {
        $configByNamespace = [];
        foreach ($this->resource as $config) {
            $namespace = $config::namespace();
            $key = $config::publicKey();
            $value = $config->toPublicArray($request);
            if ($value === null) {
                continue;
            }
            $configByNamespace[$namespace][$key] = $value;
        }

        return ['list' => $configByNamespace];
    }

    /**
     * Get the resource's relationships.
     *
     * @param Request|null $request
     * @return iterable
     */
    public function relationships($request): iterable
    {
        return [
        ];
    }

}
