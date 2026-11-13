<?php
declare(strict_types=1);

namespace App\Services\Announcements\Values;

/**
 * Whether the policy awaiting the user still needs their consent.
 *
 * Serialized as the `consent` attribute of the `registrations` JSON:API resource —
 * the string values are stable API surface the SPA's schema matches.
 */
enum ConsentState: string
{
    /** The user has accepted the policy revision that is currently in effect. */
    case VALID = 'valid';
    /** The user still has to accept the policy (never accepted, or accepted an older revision). */
    case REQUIRED = 'required';
}
