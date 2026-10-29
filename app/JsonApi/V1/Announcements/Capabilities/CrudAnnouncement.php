<?php

declare(strict_types=1);

namespace App\JsonApi\V1\Announcements\Capabilities;

use App\Models\User;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
use App\Services\Announcements\Values\AnnouncementForUser;
use Illuminate\Container\Attributes\CurrentUser;
use LaravelJsonApi\NonEloquent\Capabilities\CrudResource;

/**
 * CRUD capability of the `announcements` resource — the write path. The only
 * writable state is the per-user acknowledgement: `seen` / `accepted` boolean
 * attributes request a transition, and the service repository stamps the
 * server-side timestamps, so clients can never forge them.
 */
class CrudAnnouncement extends CrudResource
{
    public function __construct(
        private readonly UserAnnouncementRepository $repository,
        #[CurrentUser()]
        private readonly User $user,
    ) {
        parent::__construct();
    }

    /**
     * Applies the requested state transitions and returns the refreshed value.
     * Both attributes may occur in one PATCH; `accepted` is applied last so the
     * response carries both stamps.
     */
    public function update(AnnouncementForUser $record, array $validatedData): object
    {
        $announcementId = $record->id;

        if (!empty($validatedData['seen'])) {
            $this->repository->markSeen($this->user, $announcementId);
        }

        if (!empty($validatedData['accepted'])) {
            return $this->repository->markAccepted($this->user, $announcementId)
                ?? $record;
        }

        return $this->repository->findOneForUser($this->user, $announcementId) ?? $record;
    }
}
