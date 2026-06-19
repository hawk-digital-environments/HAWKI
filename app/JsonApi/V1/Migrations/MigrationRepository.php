<?php

namespace App\JsonApi\V1\Migrations;

use App\Models\User;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use Illuminate\Container\Attributes\CurrentUser;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class MigrationRepository extends AbstractRepository implements QueriesAll
{
    public function __construct(
        private readonly FrontendMigrationRepository $repository,
        #[CurrentUser]
        private readonly User                        $user
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        return $this->repository->findAllMigrationsToApplyForUser($this->user)
            ->firstWhere('name', $resourceId);
    }

    /**
     * @inheritDoc
     */
    public function queryAll(): QueryManyBuilder
    {
        return new GenericQueryAll(
            $this->repository->findAllMigrationsToApplyForUser($this->user)
        );
    }
}
