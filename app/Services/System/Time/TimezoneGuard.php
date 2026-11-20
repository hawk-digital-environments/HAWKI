<?php

declare(strict_types=1);

namespace App\Services\System\Time;

use App\Services\System\Time\Exceptions\InvalidTimezoneException;

/**
 * Guards the application timezone against ambiguous abbreviations.
 *
 * A timezone is considered safe when it is an IANA location zone (which applies
 * DST consistently across {@see \Symfony\Component\Clock\Clock}, Carbon and
 * Eloquent), `UTC`, or an `Etc/*` fixed-offset zone. Abbreviations such as
 * `CET`, `EST` or `GMT` are rejected because PHP treats them as fixed offsets
 * without DST via `new DateTimeZone(...)`, while Carbon's default-timezone
 * resolution applies DST — producing a one-hour wall-clock drift that silently
 * corrupts debounce windows and stored timestamps.
 */
final class TimezoneGuard
{
    /**
     * @throws InvalidTimezoneException when $timezone is not a safe IANA location timezone
     */
    public static function ensureSafe(string $timezone): void
    {
        $safe = \in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL), true)
            || 'UTC' === $timezone
            || str_starts_with($timezone, 'Etc/');

        if (!$safe) {
            throw InvalidTimezoneException::forTimezone($timezone);
        }
    }
}
