<?php
declare(strict_types=1);

namespace App\JsonApi\V1\Registrations;

use App\Services\Auth\Value\Registration;
use LaravelJsonApi\Contracts\Server\Server;
use LaravelJsonApi\Contracts\Store\Repository;
use LaravelJsonApi\Core\Schema\Schema;
use LaravelJsonApi\NonEloquent\Fields\Attribute;
use LaravelJsonApi\NonEloquent\Fields\ID;

/**
 * Schema of the `registrations` JSON:API resource — the singleton `hawki`.
 * `GET /registrations/hawki` reports the policy awaiting consent, `POST /registrations`
 * completes the registration (policy consent + keychain + backup in one transaction).
 */
class RegistrationSchema extends Schema
{
    public static string $model = Registration::class;

    public function __construct(
        private readonly RegistrationRepository $repository,
        Server $server,
    ) {
        parent::__construct($server);
    }

    public static function type(): string
    {
        return 'registrations';
    }

    public function fields(): array
    {
        return [
            ID::make()->matchAs('hawki'),
            // Readable state…
            Attribute::make('policy'),
            Attribute::make('consent'),
            // …and the write-only surface of the completion request.
            Attribute::make('keychain'),
            Attribute::make('backup'),
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
        // The request class gates the registration-in-progress requirement; the policy
        // document itself is public by nature.
        return false;
    }
}
