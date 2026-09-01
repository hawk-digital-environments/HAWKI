<?php

namespace App\JsonApi\V1\Announcements;

use App\Models\User;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use Illuminate\Container\Attributes\CurrentUser;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\NonEloquent\AbstractRepository;

class AnnouncementRepository extends AbstractRepository implements QueriesAll
{
    public function __construct(
        private readonly UserAnnouncementRepository $repository,
        #[CurrentUser]
        private readonly User                       $user
    )
    {
    }

    /**
     * @inheritDoc
     */
    public function find(string $resourceId): ?object
    {
        if (!ctype_digit($resourceId)) {
            return null;
        }

        return $this->repository->findOneForUser($this->user, (int)$resourceId);
    }

    /**
     * @inheritDoc
     */
    public function queryAll(): QueryManyBuilder
    {
        return new GenericQueryAll(fn() => $this->repository->findAllForUser($this->user));
    }
}
