<?php

namespace App\JsonApi\V1\TranslationLabels;

use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property TranslationLabelsDto $resource
 */
class TranslationLabelResource extends JsonApiResource
{
    /**
     * Returns a unique id to identify this resource
     */
    public function id(): string
    {
        return strtolower($this->resource->locale->htmlLang);
    }

    /**
     * Get the resource's attributes.
     *
     * @param Request|null $request
     */
    public function attributes($request): iterable
    {
        return [
            'locale' => $this->resource->locale->htmlLang,
            'labels' => $this->resource->labels,
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
