<?php
declare(strict_types=1);

namespace App\Services\Assistant\Listeners;

use App\Services\Assistant\Repositories\AssistantRepository;
use App\Services\Users\Events\UserRemovedEvent;

/**
 * Cleans up a removed user's assistant data: draft/private assistants are
 * deleted (public ones survive) and the user's pivot rows on foreign
 * assistants (favorites, received shares) are detached.
 *
 * Dormant by design: the user-removal workflow does not dispatch
 * {@see UserRemovedEvent} yet — see the TODO in
 * {@see \App\Services\Profile\ProfileService::deleteUserData()}.
 */
readonly class UserRemovalListener
{
    public function __construct(
        private AssistantRepository $repository,
    ) {
    }

    public function handle(UserRemovedEvent $event): void
    {
        $this->repository->deleteNonPublicForCreator($event->user);
        $this->repository->detachPivotsForUser($event->user);
    }
}
