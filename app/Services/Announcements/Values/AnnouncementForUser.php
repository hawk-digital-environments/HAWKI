<?php
declare(strict_types=1);


namespace App\Services\Announcements\Values;

use App\Policies\AnnouncementPolicy;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;

/**
 * Represents a single announcement as seen by one concrete user.
 *
 * Delivered to the frontend via the `announcements` JSON:API resource. Combines the
 * announcement itself with its localized markdown `$content` and the per-user pivot
 * state (`$seenAt` / `$acceptedAt`), so the frontend can decide which announcements
 * are still pending (`$isActive` and not yet accepted) and render the history list.
 */
#[UsePolicy(AnnouncementPolicy::class)]
class AnnouncementForUser
{
    public function __construct(
        public int              $id,
        public string           $title,
        public string           $type,
        public bool             $isForced,
        public ?string          $anchor,
        public ?CarbonInterface $startsAt,
        public ?CarbonInterface $expiresAt,
        /** True while the announcement is inside its `starts_at`/`expires_at` window. */
        public bool             $isActive,
        /** Localized markdown body, resolved from `resources/announcements/{view}/{lang}.md`. */
        public string           $content,
        public ?CarbonInterface $seenAt,
        public ?CarbonInterface $acceptedAt,
        /** How many users have seen this announcement, across all users. */
        public int              $seenCount
    )
    {
    }
}
