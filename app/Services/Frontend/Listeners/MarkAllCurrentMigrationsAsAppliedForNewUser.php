<?php
declare(strict_types=1);


namespace App\Services\Frontend\Listeners;


use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Users\Events\UserCreatedEvent;

/**
 * Marks every existing frontend migration as already applied for a newly created user.
 *
 * New users start with a clean slate — their data is already in the current format,
 * so there is nothing for them to migrate. Without this listener they would be presented
 * with all historical frontend migrations on first login.
 */
readonly class MarkAllCurrentMigrationsAsAppliedForNewUser
{
    public function __construct(
        private FrontendMigrationRepository        $migrationRepository,
        private AppliedFrontendMigrationRepository $appliedMigrationRepository
    )
    {
    }

    /**
     * Fires on `UserCreatedEvent` and bulk-marks all current migrations as applied
     * for the freshly created user.
     */
    public function handle(UserCreatedEvent $event): void
    {
        $this->appliedMigrationRepository->applyAllForNewUser(
            $this->migrationRepository->findAll(),
            $event->user
        );
    }
}
