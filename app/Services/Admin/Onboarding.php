<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Validation\ValidationException;

/**
 * The welcome steps new users go through before setting up their passkey: built-in steps of
 * HAWKI and steps an administrator wrote. The passkey step always follows and is not part of it.
 */
final class Onboarding
{
    public const MAX_STEPS = 8;

    /** Built-in steps that can be placed in the list; each at most once. */
    public const BUILTIN = ['encryption', 'groups'];

    /**
     * Checks a list of steps and returns it in its canonical shape; invalid steps are dropped.
     * With `$strict`, an invalid step is an error instead.
     *
     * A step is `{type: builtin, step, …}` or `{type: custom, …}`, both with an `emoji` of up to 16,
     * a `title` of up to 80 and a `text` of up to 600 characters, and an `action` (the button label)
     * of up to 40 characters. A custom step needs a title and a text; on a built-in step every one of
     * them is optional and replaces the translated default it stands for.
     *
     * @return list<array{type: string, step?: string, emoji: string|null, title: string|null, text: string|null, action: string|null}>
     */
    public static function steps(mixed $list, bool $strict = false): array
    {
        $steps = [];
        $placed = [];

        foreach (\is_array($list) ? array_values($list) : [] as $entry) {
            $step = match ($entry['type'] ?? null) {
                'builtin' => \in_array($entry['step'] ?? null, self::BUILTIN, true) && !\in_array($entry['step'], $placed, true)
                    ? self::texts($entry, required: false, into: ['type' => 'builtin', 'step' => $entry['step']])
                    : null,
                'custom' => self::texts($entry, required: true, into: ['type' => 'custom']),
                default => null,
            };

            if (null === $step) {
                if ($strict) {
                    throw ValidationException::withMessages(['value' => __('admin.onboarding.invalid')]);
                }

                continue;
            }

            if ('builtin' === $step['type']) {
                $placed[] = $step['step'];
            }

            $steps[] = $step;
        }

        return $steps;
    }

    private static function texts(array $entry, bool $required, array $into): ?array
    {
        $emoji = trim((string) ($entry['emoji'] ?? ''));
        $title = trim((string) ($entry['title'] ?? ''));
        $text = trim((string) ($entry['text'] ?? ''));
        $action = trim((string) ($entry['action'] ?? ''));

        if (($required && ('' === $title || '' === $text))
            || mb_strlen($title) > 80 || mb_strlen($text) > 600 || mb_strlen($emoji) > 16 || mb_strlen($action) > 40) {
            return null;
        }

        $empty = static fn (string $value): ?string => '' === $value ? null : $value;

        return $into + ['emoji' => $empty($emoji), 'title' => $empty($title), 'text' => $empty($text), 'action' => $empty($action)];
    }
}
