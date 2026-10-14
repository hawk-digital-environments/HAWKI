<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Validation\ValidationException;

/**
 * The news administrators publish on the sign-in page: a short list of dated entries.
 */
final class LoginNews
{
    public const MAX_ENTRIES = 10;

    /**
     * Checks a list of news entries and returns it in its canonical shape, newest first; invalid
     * entries are dropped. With `$strict`, an invalid entry is an error instead.
     *
     * An entry is a `date` (`YYYY-MM-DD`), a `title` of up to 80 characters and an optional `text`
     * of up to 280 characters.
     *
     * @return list<array{date: string, title: string, text: string|null}>
     */
    public static function entries(mixed $list, bool $strict = false): array
    {
        $entries = [];

        foreach (\is_array($list) ? array_values($list) : [] as $entry) {
            $date = $entry['date'] ?? null;
            $title = trim((string) ($entry['title'] ?? ''));
            $text = trim((string) ($entry['text'] ?? ''));
            $validDate = \is_string($date) && 1 === preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date)
                && checkdate((int) substr($date, 5, 2), (int) substr($date, 8, 2), (int) substr($date, 0, 4));

            if (!$validDate || '' === $title || mb_strlen($title) > 80 || mb_strlen($text) > 280) {
                if ($strict) {
                    throw ValidationException::withMessages(['value' => __('admin.news.invalid')]);
                }

                continue;
            }

            $entries[] = ['date' => $date, 'title' => $title, 'text' => '' === $text ? null : $text];
        }

        usort($entries, static fn (array $a, array $b): int => strcmp($b['date'], $a['date']));

        return $entries;
    }
}
