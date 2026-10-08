<?php

declare(strict_types=1);

namespace App\Services\System\Time\Exceptions;

/**
 * Thrown by {@see \App\Services\System\Time\TimezoneGuard} when the configured
 * application timezone is not a valid IANA location timezone.
 *
 * Timezone abbreviations such as `CET` or `EST` are interpreted as fixed
 * offsets without DST by some components (e.g. the {@see \Symfony\Component\Clock\Clock}
 * backing {@see \App\Services\System\Time\CarbonClock}) while Carbon/Eloquent
 * apply DST, causing a timestamp drift that corrupts debounce windows and
 * audit trails. This exception makes such a misconfiguration fail loud and
 * early at boot.
 */
class InvalidTimezoneException extends \RuntimeException
{
    public static function forTimezone(string $timezone): self
    {
        return new self(sprintf(
            'APP_TIMEZONE "%s" is not a valid IANA location timezone. '
            . 'Use a zone like "Europe/Berlin" (or "UTC"); abbreviations such as "CET"/"EST" '
            . 'are fixed offsets without DST and cause clock/Eloquent timestamp drift.',
            $timezone,
        ));
    }
}
