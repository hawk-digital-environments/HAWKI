<?php

declare(strict_types=1);

namespace App\Services\Users\Listeners;

use App\Services\Users\Events\UserRemovedEvent;
use App\Services\Users\Repositories\UserFavoriteValueRepository;

/**
 * Cleans up the removed user's favorites as part of the user-removal flow.
 *
 * Deletes every `user_favorite_values` row of the user across all namespaces and
 * types. Unlike settings, favorites have no session/runtime storage, so the
 * database rows are the only thing to clean up.
 */
class DeleteUserFavoriteValues
{
    public function __construct(private readonly UserFavoriteValueRepository $repository)
    {
    }

    public function handle(UserRemovedEvent $event): void
    {
        $this->repository->deleteAllForUser($event->user);
    }
}
