<?php

declare(strict_types=1);

namespace App\JsonApi\V1\UserSettings;

use App\Services\Users\Settings\Values\NamespacedUserSettings;
use Illuminate\Http\Request;
use LaravelJsonApi\Core\Resources\JsonApiResource;

/**
 * @property NamespacedUserSettings $resource
 */
class UserSettingResource extends JsonApiResource
{
    public function id(): string
    {
        return $this->resource->namespace;
    }

    /**
     * Get the resource's attributes.
     *
     * One attribute per settings class of the namespace, keyed by the class's public
     * key and containing its typed property values. User settings are always public —
     * there is no visibility gating and no secret filtering.
     *
     * @param null|Request $request
     */
    public function attributes($request): iterable
    {
        $attributes = [];

        foreach ($this->resource->settings as $publicKey => $settings) {
            $attributes[$publicKey] = $settings->toPublicValues();
        }

        return $attributes;
    }

    /**
     * Get the resource's relationships.
     *
     * @param null|Request $request
     */
    public function relationships($request): iterable
    {
        yield from [];
    }
}
