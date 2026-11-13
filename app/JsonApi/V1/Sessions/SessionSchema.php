<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Sessions;

use App\Services\Auth\Value\Session;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

/**
 * Schema of the `sessions` JSON:API resource — the login/logout lifecycle. `POST /sessions`
 * logs in (credentials mode), `DELETE /sessions/hawki` logs out. There is no index and no
 * readable representation: the current connection state lives on the `connections` resource.
 */
class SessionSchema extends Schema
{
    public static string $model = Session::class;

    public function __construct(
        private readonly SessionRepository $repository,
        Server $server,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'sessions';
    }

    public function fields(): array
    {
        return [
            // Pinned to the only valid id so the singleton cannot be addressed arbitrarily.
            ID::make()->matchAs('hawki'),
            // Write-only surface of the login: never serialized, validated only on store.
            Attribute::make('account'),
            Attribute::make('password'),
        ];
    }

    public function repository(): ?Repository
    {
        return $this->repository
            ->withServer($this->server)
            ->withSchema($this);
    }

    public function authorizable(): bool
    {
        // Login must be reachable by unauthenticated visitors; the request class gates
        // the browser-session requirement.
        return false;
    }
}
