<?php

namespace App\JsonApi\V1\Auths;

use App\Services\Auth\AuthInfoFactory;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class AuthRepository extends AbstractRepository
{
    /**
     * The only readable resource. Mirrors the connection resource's `hawki` id so both describe
     * "this instance" under the same name.
     */
    private const string HAWKI_ID = 'hawki';

    public function __construct(
        private readonly AuthInfoFactory $factory
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        if ($resourceId !== self::HAWKI_ID) {
            return null;
        }

        return $this->factory->createHawkiAuthInfo();
    }
}
