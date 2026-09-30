<?php
declare(strict_types=1);


namespace App\Services\Announcements\Repositories;


use App\Models\Announcements\AnnouncementUser;
use App\Models\User;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use DateTimeInterface;

/**
 * Reads and writes the `announcement_user` pivot as a consent record.
 *
 * Beyond the timestamps the pivot always had, a consent row states *what* was consented to:
 * `content_hash` is the hash of the normalized policy text the user accepted and `locale` the
 * language they read it in. Without those, an accepted policy from two text revisions ago is
 * indistinguishable from a current one.
 *
 * @extends AbstractRepository<AnnouncementUser>
 */
#[UseModel(AnnouncementUser::class)]
class PolicyConsentRepository extends AbstractRepository
{
    public function findOneForUser(User $user, int $announcementId): ?AnnouncementUser
    {
        return $this->getQuery()
            ->where('user_id', $user->id)
            ->where('announcement_id', $announcementId)
            ->first();
    }

    /**
     * Records that `$user` has seen and accepted the given revision of an announcement.
     *
     * `seen_at` is only set on the first write — when it was first shown does not change by
     * accepting it again — while acceptance always reflects the latest consent, because that is
     * the one that refers to `$contentHash`.
     */
    public function recordConsent(
        User              $user,
        int               $announcementId,
        string            $locale,
        string            $contentHash,
        DateTimeInterface $now
    ): AnnouncementUser
    {
        $existing = $this->findOneForUser($user, $announcementId);

        if ($existing !== null) {
            $existing->update([
                'seen_at' => $existing->seen_at ?? $now,
                'accepted_at' => $now,
                'locale' => $locale,
                'content_hash' => $contentHash,
            ]);

            return $existing;
        }

        return $this->getQuery()->create([
            'announcement_id' => $announcementId,
            'user_id' => $user->id,
            'seen_at' => $now,
            'accepted_at' => $now,
            'locale' => $locale,
            'content_hash' => $contentHash,
        ]);
    }
}
