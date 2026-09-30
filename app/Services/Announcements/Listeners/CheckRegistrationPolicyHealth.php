<?php

declare(strict_types=1);

namespace App\Services\Announcements\Listeners;

use App\Services\Announcements\RegistrationPolicyService;
use App\Services\Announcements\Exceptions\AnnouncementExceptionInterface;
use App\Services\System\Health\Events\HealthCheckEvent;
use App\Services\System\Health\Value\HealthCheckResult;

/** Read policy records and content files during deep checks; quick probes only check connectivity. */
readonly class CheckRegistrationPolicyHealth
{
    public function __construct(private RegistrationPolicyService $policies) {}

    public function handle(HealthCheckEvent $event): void
    {
        try {
            $this->policies->resolve();
            $result = new HealthCheckResult('registration_policy', HealthCheckResult::STATUS_OK, 'Registration policy is available.');
        } catch (AnnouncementExceptionInterface) {
            $result = new HealthCheckResult('registration_policy', HealthCheckResult::STATUS_WARNING, 'Registration is unavailable until an active policy with readable content is published.');
        }
        $event->addResult($result);
    }
}
