<?php

namespace App\JsonApi\V1\AiModelFlags;

use App\Services\Ai\Models\Flags\Values\ModelFlagDefinition;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property ModelFlagDefinition $resource
 */
class AiModelFlagResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return $this->resource->key;
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'title_label' => $this->resource->titleLabel,
            'description_label' => $this->resource->descriptionLabel,
            'color_code' => $this->resource->colorCode,
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
