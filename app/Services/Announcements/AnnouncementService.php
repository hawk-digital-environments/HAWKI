<?php

declare(strict_types=1);

namespace App\Services\Announcements;

use App\Models\Announcements\Announcement;
use App\Models\User;
use App\Services\Announcements\Repositories\UserAnnouncementRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

readonly class AnnouncementService
{
    public function __construct(
        private RegistrationPolicyPublishService $policyPublisher,
        private UserAnnouncementRepository $userAnnouncements,
        private AnnouncementContentResolver $contentResolver,
        private AnnouncementPublicationRules $publicationRules,
    ) {
    }

    /**
     * Create a new announcement.
     *
     * Example:
     * $service->createAnnouncement('announcements.terms_update', 'info', true);
     *
     * @param array<string, string>|null $excerpt Optional list teaser keyed by locale code (e.g.
     *        `['de_DE' => '…']`); without one the frontend derives it from the content. Not used
     *        for policies, which are not listed in the announcements feed.
     * @throws \App\Services\Announcements\Exceptions\OverlappingPolicyException when publishing a
     *                                                                           policy whose validity window overlaps an already published one
     */
    public function createAnnouncement(
        string $title,
        string $view,
        string $type = 'info',
        bool $isForced = false,
        bool $isGlobal = true,
        ?array $targetUsers = null,
        ?string $anchor = null,
        ?string $startsAt = null,
        ?string $expiresAt = null,
        ?array $excerpt = null,
    ): Announcement {
        // A policy is the one document users consent to, so two of them may never be in effect at
        // the same time. Catching that here means the operator sees it while publishing, instead
        // of users consenting to whichever policy the tie-break happened to pick.
        if ('policy' === $type && $isGlobal) {
            return $this->policyPublisher->publish(
                $title,
                $view,
                $isForced,
                $anchor,
                $startsAt,
                $expiresAt,
            );
        }

        $data = [
            'title' => $title,
            'view' => $view,
            'type' => $type,
            'is_forced' => $isForced,
            'is_global' => $isGlobal,
            'target_users' => $targetUsers,
            'anchor' => $anchor,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'excerpt' => $excerpt ?: null,
            'content' => null,
            'is_published' => true,
        ];
        $this->publicationRules->validate(new Announcement(), $data);

        return Announcement::create($data);
    }

    public function getUserAnnouncements()
    {
        $announcements = Auth::user()->unreadAnnouncements();
        // Collect force announcements
        $forceAnnouncements = [];

        foreach ($announcements as $announcement) {
            if (true === $announcement->is_forced && null === $announcement->anchor) {
                $forceAnnouncements[] = $announcement;
            }
        }

        Session::put('force_announcements', $forceAnnouncements);

        return $announcements->map(static function ($ann) {
            return [
                'id' => $ann->id,
                'title' => $ann->title,
                'type' => $ann->type,
                'isForced' => $ann->is_forced,
                'anchor' => $ann->anchor,
                'expires_at' => $ann->expires_at,
            ];
        });
    }

    /**
     * Find active announcements (system-wide).
     */
    public function getActiveAnnouncements(): Collection
    {
        $now = now();

        return Announcement::query()
            ->where('is_published', true)
            ->where(static function ($q) use ($now): void {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(static function ($q) use ($now): void {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            })
            ->get();
    }

    public function fetchLatestPolicy(): Announcement
    {
        return $this->getActiveAnnouncements()->where('type', 'policy')->firstOrFail();
    }

    /**
     * Validate user access to announcement.
     */
    public function validateUserAccess(User $user, Announcement $announcement): bool
    {
        return $this->userAnnouncements->findActiveAnnouncementForUser($user, (int) $announcement->id) !== null;
    }

    /**
     * Get announcement for rendering with access validation.
     */
    public function getAnnouncementForUser(User $user, int $announcementId): ?Announcement
    {
        return $this->userAnnouncements->findActiveAnnouncementForUser($user, $announcementId);
    }

    /**
     * Render announcement Blade and return to frontend.
     */
    public function renderAnnouncement(Announcement $announcement): string
    {
        return $this->contentResolver->resolve($announcement)?->text ?? '';
    }

    /**
     * Mark announcement as seen for user.
     */
    public function markAnnouncementAsSeen(User $user, int $announcementId): bool
    {
        try {
            return $this->userAnnouncements->markSeen($user, $announcementId) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Mark announcement as accepted for user.
     */
    public function markAnnouncementAsAccepted(User $user, int $announcementId): bool
    {
        try {
            return $this->userAnnouncements->markAccepted($user, $announcementId) !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
}
