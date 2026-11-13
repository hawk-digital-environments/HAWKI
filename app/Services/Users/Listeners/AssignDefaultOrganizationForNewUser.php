<?php

declare(strict_types=1);

namespace App\Services\Users\Listeners;

use App\Services\Users\Events\UserCreatedEvent;
use App\Services\Users\Repositories\UserOrganizationRepository;
use Illuminate\Container\Attributes\Config;
use Psr\Log\LoggerInterface;

/**
 * Attaches every newly created user to the configured default organization as a member —
 * the initial migration only backfilled the users that existed at migration time.
 */
readonly class AssignDefaultOrganizationForNewUser
{
    public function __construct(
        private UserOrganizationRepository $organizationRepository,
        #[Config('hawki.organization.default')]
        private string $defaultOrganization,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Fires on `UserCreatedEvent` and attaches the freshly created user to the default organization.
     * A missing configuration or an unknown organization never breaks the registration.
     */
    public function handle(UserCreatedEvent $event): void
    {
        if ('' === $this->defaultOrganization) {
            return;
        }

        $organization = $this->organizationRepository->findByName($this->defaultOrganization);

        if (null === $organization) {
            $this->logger->warning(\sprintf(
                'Default organization "%s" does not exist, user "%s" was created without an organization membership.',
                $this->defaultOrganization,
                $event->user->username,
            ));

            return;
        }

        $this->organizationRepository->attachUserAsMember($event->user, $organization);
    }
}
