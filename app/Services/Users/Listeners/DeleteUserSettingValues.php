<?php

declare(strict_types=1);

namespace App\Services\Users\Listeners;

use App\Services\Users\Events\UserRemovedEvent;
use App\Services\Users\Repositories\UserSettingValueRepository;

/**
 * Cleans up the removed user's settings rows as part of the user-removal flow.
 *
 * Deletes every `user_setting_values` row of the user across all namespaces. Session and
 * runtime storage entries need no cleanup — they are guest-scoped and die with the
 * session or process.
 */
class DeleteUserSettingValues
{
    public function __construct(private readonly UserSettingValueRepository $repository)
    {
    }

    public function handle(UserRemovedEvent $event): void
    {
        $this->repository->deleteAllForUser($event->user);
    }
}
