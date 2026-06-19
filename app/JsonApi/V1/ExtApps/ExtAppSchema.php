<?php

namespace App\JsonApi\V1\ExtApps;

use App\Models\ExtApp;
use App\Services\ExtApp\ExtAppUrlBuilder;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Eloquent\Fields\Str;
use LaravelJsonApi\Eloquent\Schema;

class ExtAppSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = ExtApp::class;

    /**
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            ExtAppId::make()->matchAs('\d+|[^\/]+'),
            Str::make('name'),
            Str::make('description'),
            Str::make('url'),
            Str::make('redirectOnConnectionAccepted')
                ->extractUsing(fn(ExtApp $app) => $this->getService(ExtAppUrlBuilder::class)
                    ->redirectOnConnectionAccepted($app)),
            Str::make('redirectOnConnectionDeclined')
                ->extractUsing(fn(ExtApp $app) => $this->getService(ExtAppUrlBuilder::class)
                    ->redirectOnConnectionDeclined($app)),
            Str::make('logoUrl'),
            Str::make('logoProxyUrl')
                ->extractUsing(fn(ExtApp $app) => $this->getService(ExtAppUrlBuilder::class)
                    ->logoProxyUrl($app)),
            Str::make('publicKey')
                ->extractUsing(fn(ExtApp $app) => (string)$app->app_public_key),
        ];
    }
}
