<?php
declare(strict_types=1);

namespace App\Services\Announcements;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\Repositories\PolicyAnnouncementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Singleton;
use InvalidArgumentException;

#[Singleton]
readonly class RegistrationPolicyPublishService
{
    public function __construct(
        private RegistrationPolicyService $policies,
        private PolicyAnnouncementRepository $repository,
    ) {
    }

    public function publish(
        string $title,
        string $view,
        bool $isForced = false,
        ?string $anchor = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
    ): Announcement {
        $start = $startsAt === null ? null : CarbonImmutable::parse($startsAt);
        $expiry = $expiresAt === null ? null : CarbonImmutable::parse($expiresAt);
        if ($start !== null && $expiry !== null && $expiry->lt($start)) {
            throw new InvalidArgumentException('A policy cannot expire before it starts.');
        }

        $this->policies->assertPublishable($start, $expiry);

        return $this->repository->publish($title, $view, $isForced, $anchor, $start, $expiry);
    }
}
