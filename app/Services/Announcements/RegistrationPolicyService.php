<?php
declare(strict_types=1);


namespace App\Services\Announcements;


use App\Models\Announcements\Announcement;
use App\Models\User;
use App\Services\Announcements\Exceptions\OverlappingPolicyException;
use App\Services\Announcements\Exceptions\RegistrationPolicyUnavailableException;
use App\Services\Announcements\Repositories\PolicyAnnouncementRepository;
use App\Services\Announcements\Repositories\PolicyConsentRepository;
use App\Services\Announcements\Values\RegistrationPolicy;
use App\Services\System\Time\CarbonClockInterface;
use DateTimeInterface;
use Illuminate\Container\Attributes\Singleton;

/**
 * @api
 *
 * The usage policy as the registration flow needs it: which policy is in effect, whether a user
 * has already accepted the revision that is live *now*, and recording that they did.
 *
 * Consent is checked against the current text hash, not against the announcement id alone.
 * Announcement content lives in markdown files, so an operator can change what a policy says
 * without touching the database — and a consent given for the old wording must not silently
 * count for the new one.
 */
#[Singleton]
readonly class RegistrationPolicyService
{
    /** The user has accepted the policy revision that is currently in effect. */
    public const string CONSENT_VALID = 'valid';
    /** The user still has to accept the policy (never accepted, or accepted an older revision). */
    public const string CONSENT_REQUIRED = 'required';

    public function __construct(
        private PolicyAnnouncementRepository $policyRepository,
        private PolicyConsentRepository      $consentRepository,
        private AnnouncementContentResolver  $contentResolver,
        private PolicyContentHasher          $hasher,
        private CarbonClockInterface         $clock
    )
    {
    }

    /**
     * The policy in effect right now, or null when the installation has none published.
     *
     * @throws RegistrationPolicyUnavailableException when a policy is published but has no text
     *                                                for the current or the default locale.
     */
    public function resolve(?string $requestedLocale = null): RegistrationPolicy
    {
        $announcement = $this->policyRepository->findCurrentPolicy($this->clock->now());

        if ($announcement === null) {
            throw RegistrationPolicyUnavailableException::forMissingPolicy();
        }

        return $this->toPolicy($announcement, $requestedLocale);
    }

    /**
     * Compatibility name for callers that do not choose a locale explicitly.
     *
     * @throws RegistrationPolicyUnavailableException
     */
    public function findCurrentPolicy(): RegistrationPolicy
    {
        return $this->resolve();
    }

    /**
     * Whether `$user` has accepted exactly the revision described by `$policy`.
     *
     * Requires both an `accepted_at` timestamp and a matching content hash — a consent row
     * written before the hash existed (or for an older wording) does not count.
     */
    public function hasValidConsent(User $user, RegistrationPolicy $policy): bool
    {
        $consent = $this->consentRepository->findOneForUser($user, $policy->id);

        return $consent !== null
            && $consent->accepted_at !== null
            && $consent->content_hash === $policy->hash;
    }

    /**
     * Marks `$policy` as seen and accepted by `$user`, together with the revision and language
     * they accepted.
     */
    public function recordConsent(User $user, RegistrationPolicy $policy): void
    {
        $this->consentRepository->recordConsent(
            $user,
            $policy->id,
            $policy->locale,
            $policy->hash,
            $this->clock->now()
        );
    }

    public function acceptAnnouncement(User $user, Announcement $announcement): void
    {
        $this->recordConsent($user, $this->toPolicy($announcement, null));
    }

    /**
     * Guards publishing a policy: at no point in time may two policies be in effect.
     *
     * A null bound is open ended, so a policy without an expiry conflicts with every later one —
     * which is exactly the mistake this catches: publishing a new policy while the old one never
     * expires.
     *
     * @throws OverlappingPolicyException
     */
    public function assertPublishable(
        ?DateTimeInterface $startsAt,
        ?DateTimeInterface $expiresAt,
        ?int               $ignoreId = null
    ): void
    {
        $conflicts = $this->policyRepository->findPoliciesOverlapping($startsAt, $expiresAt, $ignoreId);

        if ($conflicts->isNotEmpty()) {
            throw OverlappingPolicyException::forConflicts($conflicts);
        }
    }

    /**
     * @throws RegistrationPolicyUnavailableException
     */
    private function toPolicy(Announcement $announcement, ?string $requestedLocale): RegistrationPolicy
    {
        $content = $this->contentResolver->resolve($announcement, $requestedLocale);

        if ($content === null) {
            throw RegistrationPolicyUnavailableException::forMissingContent(
                $announcement->id,
                $announcement->view
            );
        }

        $text = $this->hasher->normalize($content->text);

        if (trim($text) === '') {
            throw RegistrationPolicyUnavailableException::forEmptyContent(
                $announcement->id,
                $announcement->view,
                $content->locale
            );
        }

        return new RegistrationPolicy(
            id: $announcement->id,
            locale: $content->locale,
            text: $text,
            hash: $this->hasher->hash($text)
        );
    }
}
