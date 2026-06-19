<?php
declare(strict_types=1);


namespace App\Services\Frontend\Listeners;


use App\Services\Frontend\Migrations\Repositories\AppliedFrontendMigrationRepository;
use App\Services\Frontend\Migrations\Repositories\FrontendMigrationRepository;
use App\Services\Users\Events\UserCreatedEvent;

readonly class MarkAllCurrentMigrationsAsAppliedForNewUser
{
    public function __construct(
        private FrontendMigrationRepository        $migrationRepository,
        private AppliedFrontendMigrationRepository $appliedMigrationRepository
    )
    {
    }

    public function handle(UserCreatedEvent $event): void
    {
        $this->appliedMigrationRepository->applyAllForNewUser(
            $this->migrationRepository->findAll(),
            $event->user
        );
    }
}
