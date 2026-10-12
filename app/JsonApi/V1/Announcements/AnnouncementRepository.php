<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Announcements;

use App\JsonApi\V1\Announcements\Capabilities\CrudAnnouncement;
use App\Models\User;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
use App\Services\System\JsonApi\NonEloquent\Capabilities\GenericQueryAll;
use Illuminate\Container\Attributes\CurrentUser;
use LaravelJsonApi\Contracts\Store\QueriesAll;
use LaravelJsonApi\Contracts\Store\QueryManyBuilder;
use LaravelJsonApi\Contracts\Store\UpdatesResources;
use LaravelJsonApi\NonEloquent\AbstractRepository;
use LaravelJsonApi\NonEloquent\Concerns\HasCrudCapability;

class AnnouncementRepository extends AbstractRepository implements QueriesAll, UpdatesResources
{
    use HasCrudCapability;

    public function __construct(
        private readonly UserAnnouncementRepository $repository,
        #[CurrentUser()]
        private readonly User $user,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function find(string $resourceId): ?object
    {
        if (!ctype_digit($resourceId)) {
            return null;
        }

        return $this->repository->findOneForUser($this->user, (int) $resourceId);
    }

    /**
     * {@inheritDoc}
     */
    public function queryAll(): QueryManyBuilder
    {
        return new GenericQueryAll(fn () => $this->repository->findAllForUser($this->user));
    }

    /**
     * {@inheritDoc}
     */
    protected function crud(): CrudAnnouncement
    {
        return CrudAnnouncement::make();
    }
}
