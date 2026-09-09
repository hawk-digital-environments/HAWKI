<?php

declare(strict_types=1);

namespace App\Services\Announcements\Listeners;

use App\Services\Announcements\RegistrationPolicyService;
use App\Services\System\Health\Events\HealthCheckEvent;
use App\Services\System\Health\Events\QuickHealthCheckEvent;
use App\Services\System\Health\Value\HealthCheckResult;

/** Registration policy checks read the local database and content files only. */
readonly class CheckRegistrationPolicyHealth
{
    public function __construct(private RegistrationPolicyService $policies) {}

    public function handle(HealthCheckEvent|QuickHealthCheckEvent $event): void
    {
        try {
            $this->policies->resolve();
            $result = new HealthCheckResult('registration_policy', HealthCheckResult::STATUS_OK, 'Registration policy is available.');
        } catch (\Throwable) {
            $result = new HealthCheckResult('registration_policy', HealthCheckResult::STATUS_WARNING, 'Registration is unavailable until an active policy with readable content is published.');
        }
        $event->addResult($result);
    }
}
