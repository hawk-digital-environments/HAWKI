<?php

namespace App\JsonApi\V1\AiToolCapabilities;

use App\Services\Ai\Tools\Values\AiToolCapabilityDefinition;
use App\Services\System\JsonApi\ValueSerializer;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property AiToolCapabilityDefinition $resource
 */
class AiToolCapabilityResource extends JsonApiResource
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
            'default_value' => $this->resource->defaultValue->value,
            'title_label' => $this->resource->titleLabel,
            'description_label' => $this->resource->descriptionLabel,
            'icon_path' => ValueSerializer::localFileAsDataUrl($this->resource->iconPath),
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
