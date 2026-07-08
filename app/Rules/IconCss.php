<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates a user-supplied CSS declaration block for assistant avatars.
 *
 * The value is rendered into the DOM of users viewing the assistant, so this
 * enforces a strict property whitelist and bans every value-level injection
 * vector (url(), @import, expression(), markup, etc.). Only safe,
 * presentational properties are permitted.
 */
final class IconCss implements ValidationRule
{
    public const int MAX_LENGTH = 1000;

    /**
     * Substrings that may never appear in the value, regardless of context.
     * Case-insensitive.
     */
    private const array DENYLIST = [
        'url(',
        'image(',
        'image-set(',
        'cross-fade(',
        'element(',
        '@',
        '<',
        '>',
        '{',
        '}',
        '/*',
        '*/',
        'expression(',
        'javascript',
        'vbscript',
        '`',
        '\\',
        '&',
    ];

    /**
     * CSS properties that are safe to expose to other viewers. Anything not
     * listed here (position, z-index, display, pointer-events, behavior, …) is
     * rejected.
     */
    private const array ALLOWED_PROPERTIES = [
        'background',
        'background-color',
        'background-image',
        'background-size',
        'background-position',
        'background-repeat',
        'background-clip',
        'background-origin',
        'background-attachment',
        'opacity',
        'color',
        'font',
        'font-size',
        'font-weight',
        'font-family',
        'font-style',
        'line-height',
        'letter-spacing',
        'word-spacing',
        'text-align',
        'text-decoration',
        'white-space',
        'margin',
        'margin-top',
        'margin-right',
        'margin-bottom',
        'margin-left',
        'padding',
        'padding-top',
        'padding-right',
        'padding-bottom',
        'padding-left',
        'border',
        'border-color',
        'border-width',
        'border-style',
        'border-radius',
        'border-top',
        'border-right',
        'border-bottom',
        'border-left',
        'outline',
        'box-shadow',
        'text-shadow',
    ];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        if ($value === '') {
            return;
        }

        if (strlen($value) > self::MAX_LENGTH) {
            $fail('The :attribute must not exceed '.self::MAX_LENGTH.' characters.');

            return;
        }

        $lower = strtolower($value);

        foreach (self::DENYLIST as $token) {
            if (str_contains($lower, $token)) {
                $fail('The :attribute contains a forbidden CSS token.');

                return;
            }
        }

        $declarations = array_filter(
            array_map('trim', explode(';', $value)),
            static fn (string $declaration) => $declaration !== '',
        );

        if ($declarations === []) {
            $fail('The :attribute must contain at least one CSS declaration.');

            return;
        }

        foreach ($declarations as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                $fail('The :attribute contains an invalid CSS declaration.');

                return;
            }

            $property = strtolower(trim($parts[0]));
            $val = trim($parts[1]);

            if ($val === '') {
                $fail('The :attribute contains an empty CSS value.');

                return;
            }

            if (! in_array($property, self::ALLOWED_PROPERTIES, true)) {
                $fail("The :attribute contains a disallowed CSS property `{$property}`.");

                return;
            }
        }
    }
}
