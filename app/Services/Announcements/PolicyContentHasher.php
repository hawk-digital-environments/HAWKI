<?php
declare(strict_types=1);


namespace App\Services\Announcements;


use Illuminate\Container\Attributes\Singleton;

/**
 * Turns policy markdown into a stable hash that identifies "this exact text".
 *
 * The hash is what a consent record is checked against, so it has to survive edits that do not
 * change what the user reads and it has to change when anything else does. Two normalisations
 * get us there:
 *
 * - **Line endings.** The same file checked out on Windows and on Linux differs in every line
 *   (`\r\n` vs `\n`). A consent must not expire because of a git checkout.
 * - **Trailing whitespace.** Editors and formatters add and remove it invisibly, per line and at
 *   the end of the file.
 *
 * Everything else — including leading whitespace, which is structural in markdown — is
 * significant.
 */
#[Singleton]
readonly class PolicyContentHasher
{
    public function hash(string $text): string
    {
        return hash('sha256', $this->normalize($text));
    }

    /**
     * The canonical form the hash is taken over. Public because a mismatch is easier to debug
     * when you can look at what was actually hashed.
     */
    public function normalize(string $text): string
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = array_map(
            static fn(string $line): string => rtrim($line, " \t\v\0"),
            explode("\n", $text)
        );

        return rtrim(implode("\n", $lines));
    }
}
