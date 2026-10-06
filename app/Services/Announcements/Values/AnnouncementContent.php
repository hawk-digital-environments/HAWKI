<?php
declare(strict_types=1);


namespace App\Services\Announcements\Values;


/**
 * The markdown body of an announcement together with the locale it was actually resolved for.
 *
 * The requested locale and `$locale` differ whenever the fallback kicked in, and the difference
 * matters: a consent record has to store the language the user really saw, not the one they had
 * selected.
 */
readonly class AnnouncementContent
{
    public function __construct(
        /** Locale code of the file that was used, e.g. `de_DE`. */
        public string $locale,
        public string $text
    )
    {
    }
}
