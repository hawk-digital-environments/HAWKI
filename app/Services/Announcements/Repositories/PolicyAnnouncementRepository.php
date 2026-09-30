<?php
declare(strict_types=1);


namespace App\Services\Announcements\Repositories;


use App\Models\Announcements\Announcement;
use App\Services\System\Database\Eloquent\Repositories\AbstractRepository;
use App\Services\System\Database\Eloquent\Repositories\Attributes\UseModel;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queries the announcement rows that act as the platform's usage policy.
 *
 * A policy is an announcement with `type = policy` that is global — a policy targeted at a few
 * users would mean some accounts consent to nothing, which is not a state the registration flow
 * can represent.
 *
 * @extends AbstractRepository<Announcement>
 */
#[UseModel(Announcement::class)]
class PolicyAnnouncementRepository extends AbstractRepository
{
    public const string TYPE_POLICY = 'policy';

    /**
     * The one policy in effect at `$now`, or null when the installation has none.
     *
     * Ordering is `COALESCE(starts_at, created_at) DESC, id DESC`: a policy without an explicit
     * start has been in effect since it was created, so its creation time is its start. The id
     * breaks ties, which makes the result deterministic for two policies published in the same
     * second — without it, the answer to "which policy do I have to accept?" could differ
     * between two requests.
     */
    public function findCurrentPolicy(DateTimeInterface $now): ?Announcement
    {
        return $this->queryActivePolicies($now)
            ->orderByRaw('COALESCE(starts_at, created_at) DESC')
            ->orderByDesc('id')
            ->first();
    }

    public function findOnePolicyById(int $id): ?Announcement
    {
        return $this->getQuery()
            ->where('type', self::TYPE_POLICY)
            ->whereKey($id)
            ->first();
    }

    public function publish(
        string $title,
        string $view,
        bool $isForced,
        ?string $anchor,
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $expiresAt,
    ): Announcement {
        return $this->getQuery()->create([
            'title' => $title,
            'view' => $view,
            'type' => self::TYPE_POLICY,
            'is_forced' => $isForced,
            'is_global' => true,
            'target_users' => null,
            'anchor' => $anchor,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ]);
    }

    /**
     * All global policies whose validity window overlaps `[$startsAt, $expiresAt]`, where a null
     * bound means "open ended".
     *
     * @param int|null $ignoreId Announcement to leave out (the one being edited).
     * @return Collection<int, Announcement>
     */
    public function findPoliciesOverlapping(
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $expiresAt,
        ?int               $ignoreId = null
    ): Collection
    {
        $query = $this->getQuery()
            ->where('type', self::TYPE_POLICY)
            ->where('is_global', true)
            ->when($ignoreId !== null, static fn(Builder $q) => $q->whereKeyNot($ignoreId));

        // Two windows overlap unless one ends before the other begins. A null bound is open
        // ended (-inf / +inf) and can never end early, so the comparison is skipped for it —
        // both for the stored policy and for the window we are asking about.
        if ($expiresAt !== null) {
            $query->where(static function (Builder $q) use ($expiresAt) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $expiresAt);
            });
        }

        if ($startsAt !== null) {
            $query->where(static function (Builder $q) use ($startsAt) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>=', $startsAt);
            });
        }

        return $query->orderBy('id')->get();
    }

    /**
     * @return Builder<Announcement>
     */
    private function queryActivePolicies(DateTimeInterface $now): Builder
    {
        return $this->getQuery()
            ->where('type', self::TYPE_POLICY)
            ->where('is_global', true)
            ->where(static function (Builder $query) use ($now) {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(static function (Builder $query) use ($now) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>=', $now);
            });
    }
}
