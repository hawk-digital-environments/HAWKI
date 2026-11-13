<?php

namespace App\JsonApi\V1\Auths;

use App\Services\Auth\Value\AuthInfo;
use App\Services\System\Container\ServiceLocatorTrait;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

class AuthSchema extends Schema
{
    use ServiceLocatorTrait;

    /**
     * The model the schema corresponds to.
     *
     * @var string
     */
    public static string $model = AuthInfo::class;

    /**
     * The resource type, and with it the URI segment.
     *
     * Overridden because the class-name based default would pluralise this to `auths`, which
     * reads as a typo. There is only ever one of these per instance — the login description for
     * the HAWKI instance itself — so the type stays singular: `GET /auth/hawki`.
     */
    public static function type(): string
    {
        return 'auth';
    }

    /**
     * `last_error` is a nullable stable code: invalid_credentials or provider_failed.
     *
     * Get the resource fields.
     *
     * @return array
     */
    public function fields(): array
    {
        return [
            // Pinned to the single valid id rather than a general pattern, so that the custom
            // actions registered alongside it (`auth/actions/login`) cannot be swallowed by the
            // `auth/{auth}` show route, which the router matches first.
            ID::make()->matchAs('hawki'),
            Attribute::make('mode'),
            Attribute::make('start_url'),
            Attribute::make('capabilities'),
            Attribute::make('last_error'),
        ];
    }

    /**
     * Get the resource filters.
     *
     * @return array
     */
    public function filters(): array
    {
        return [
        ];
    }

    /**
     * @inheritDoc
     */
    public function repository(): ?Repository
    {
        return $this->getService(AuthRepository::class)
            ->withServer($this->server)
            ->withSchema($this);
    }

    /**
     * The resource describes how to *become* authenticated, so it has to be readable by
     * unauthenticated visitors. There is nothing user-specific in it to authorize.
     */
    public function authorizable(): bool
    {
        return false;
    }
}
