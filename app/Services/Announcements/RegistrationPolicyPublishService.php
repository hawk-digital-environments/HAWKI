<?php

declare(strict_types=1);

namespace App\Services\Announcements;

use App\Models\Announcements\Announcement;
use App\Services\Announcements\Repositories\PolicyAnnouncementRepository;
use Carbon\CarbonImmutable;
use Illuminate\Container\Attributes\Singleton;

#[Singleton()]
readonly class RegistrationPolicyPublishService
{
    public function __construct(
        private RegistrationPolicyService $policies,
        private PolicyAnnouncementRepository $repository,
        private AnnouncementPublicationRules $publicationRules,
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
        $start = null === $startsAt ? null : CarbonImmutable::parse($startsAt);
        $expiry = null === $expiresAt ? null : CarbonImmutable::parse($expiresAt);

        if (null !== $start && null !== $expiry && $expiry->lt($start)) {
            throw new \InvalidArgumentException('A policy cannot expire before it starts.');
        }

        $this->policies->assertPublishable($start, $expiry);

        $this->publicationRules->validate(new Announcement(), [
            'title' => $title,
            'view' => $view,
            'type' => PolicyAnnouncementRepository::TYPE_POLICY,
            'is_forced' => $isForced,
            'is_global' => true,
            'target_users' => null,
            'target_roles' => null,
            'content' => null,
            'is_published' => true,
            'anchor' => $anchor,
            'starts_at' => $start,
            'expires_at' => $expiry,
        ], validatePolicyWindow: false);

        return $this->repository->publish($title, $view, $isForced, $anchor, $start, $expiry);
    }
}
