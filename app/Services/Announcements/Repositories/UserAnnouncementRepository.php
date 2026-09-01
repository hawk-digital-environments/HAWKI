<?php
declare(strict_types=1);


namespace App\Services\Announcements\Repositories;

use App\Models\Announcements\Announcement;
use App\Models\Announcements\AnnouncementUser;
use App\Models\User;
use App\Services\Announcements\Values\AnnouncementForUser;
use App\Services\Translation\LocaleService;
use Illuminate\Support\Collection;

/**
 * Read/write access to announcements from the perspective of a single user,
 * backing the `announcements` JSON:API resource.
 *
 * An announcement is visible to a user when it has started and is either global,
 * lists the user in `target_users`, or already has a pivot row for the user.
 * Expired announcements stay visible so the frontend can render the history list;
 * {@see AnnouncementForUser::$isActive} tells both states apart.
 */
readonly class UserAnnouncementRepository
{
    public function __construct(
        private LocaleService $localeService
    )
    {
    }

    /**
     * @return Collection<int, AnnouncementForUser>
     */
    public function findAllForUser(User $user): Collection
    {
        $pivots = $this->findPivotsForUser($user);
        $seenCounts = $this->findSeenCounts();

        return $this->queryVisibleForUser($user)
            ->get()
            ->map(fn(Announcement $announcement) => $this->mapToValue(
                $announcement,
                $pivots->get($announcement->id),
                (int)($seenCounts[$announcement->id] ?? 0)
            ))
            ->values();
    }

    public function findOneForUser(User $user, int $announcementId): ?AnnouncementForUser
    {
        $announcement = $this->queryVisibleForUser($user)->whereKey($announcementId)->first();
        if ($announcement === null) {
            return null;
        }

        return $this->mapToValue(
            $announcement,
            $this->findPivotsForUser($user)->get($announcement->id),
            (int)($this->findSeenCounts()[$announcement->id] ?? 0)
        );
    }

    /**
     * Sets the `seen_at` timestamp for the user, or returns null if the
     * announcement does not exist or is not visible to the user.
     */
    public function markSeen(User $user, int $announcementId): ?AnnouncementForUser
    {
        if ($this->findOneForUser($user, $announcementId) === null) {
            return null;
        }

        $user->markAnnouncementAsSeen($announcementId);

        return $this->findOneForUser($user, $announcementId);
    }

    /**
     * Sets the `accepted_at` timestamp for the user, or returns null if the
     * announcement does not exist or is not visible to the user.
     */
    public function markAccepted(User $user, int $announcementId): ?AnnouncementForUser
    {
        if ($this->findOneForUser($user, $announcementId) === null) {
            return null;
        }

        $user->markAnnouncementAsAccepted($announcementId);

        return $this->findOneForUser($user, $announcementId);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Announcement>
     */
    private function queryVisibleForUser(User $user)
    {
        return Announcement::query()
            ->where(function ($q) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($q) use ($user) {
                $q->where('is_global', true)
                    ->orWhereJsonContains('target_users', $user->id)
                    ->orWhereHas('users', fn($sub) => $sub->where('user_id', $user->id));
            })
            ->orderByDesc('starts_at');
    }

    /**
     * @return Collection<int, AnnouncementUser> Pivot rows of the user keyed by announcement id.
     */
    private function findPivotsForUser(User $user): Collection
    {
        return AnnouncementUser::query()
            ->where('user_id', $user->id)
            ->get()
            ->keyBy('announcement_id');
    }

    /**
     * @return \Illuminate\Support\Collection<int, int> Number of users that saw an announcement, keyed by announcement id.
     */
    private function findSeenCounts(): Collection
    {
        return AnnouncementUser::query()
            ->whereNotNull('seen_at')
            ->selectRaw('announcement_id, COUNT(*) as aggregate')
            ->groupBy('announcement_id')
            ->pluck('aggregate', 'announcement_id');
    }

    private function mapToValue(Announcement $announcement, ?AnnouncementUser $pivot, int $seenCount): AnnouncementForUser
    {
        $started = $announcement->starts_at === null || $announcement->starts_at->lte(now());
        $expired = $announcement->expires_at !== null && $announcement->expires_at->lt(now());

        return new AnnouncementForUser(
            $announcement->id,
            $announcement->title,
            $announcement->type,
            (bool)$announcement->is_forced,
            $announcement->anchor,
            $announcement->starts_at,
            $announcement->expires_at,
            $started && !$expired,
            $this->resolveContent($announcement),
            $pivot?->seen_at,
            $pivot?->accepted_at,
            $seenCount
        );
    }

    /**
     * Loads the markdown body for the current locale, falling back to the
     * default locale. Returns an empty string when no content file exists.
     */
    private function resolveContent(Announcement $announcement): string
    {
        $candidates = [
            $this->localeService->getCurrentLocale()->lang,
            $this->localeService->getDefaultLocale()->lang,
        ];

        foreach ($candidates as $lang) {
            $file = resource_path("announcements/{$announcement->view}/$lang.md");
            if (is_file($file)) {
                return (string)file_get_contents($file);
            }
        }

        return '';
    }
}
