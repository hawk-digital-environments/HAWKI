<?php
declare(strict_types=1);

namespace App\Services\Announcements\Values;

/**
 * The closed set of announcement categories.
 *
 * Mirrors the `type` enum column of the `announcements` table (see the
 * create-announcements migration) — the database guarantees only these five values exist,
 * which is what makes the Eloquent cast on {@see \App\Models\Announcements\Announcement::$type}
 * safe. The `policy` case is the load-bearing one: accepting an announcement of that type
 * records hash-bound consent ({@see \App\Services\Announcements\RegistrationPolicyService}),
 * every other type only stamps `accepted_at` on the pivot.
 */
enum AnnouncementType: string
{
    case POLICY = 'policy';
    case NEWS = 'news';
    case SYSTEM = 'system';
    case EVENT = 'event';
    case INFO = 'info';
}
